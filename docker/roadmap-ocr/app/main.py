from __future__ import annotations

import io
import time
from typing import Literal

import pytesseract
from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from PIL import Image, ImageEnhance, ImageOps

PreprocessMode = Literal["none", "grayscale", "bw", "strong-bw", "layout-bw"]

app = FastAPI(title="f76-roadmap-ocr", version="0.1.0")


@app.get("/healthz")
def healthz() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/ocr/roadmap/scan")
async def scan_roadmap(
    image: UploadFile = File(...),
    locale: str = Form("en"),
    preprocess: PreprocessMode = Form("layout-bw"),
) -> dict:
    start = time.perf_counter()

    if not image.content_type or not image.content_type.startswith("image/"):
        raise HTTPException(status_code=400, detail="image must be an image/* upload")

    payload = await image.read()
    if not payload:
        raise HTTPException(status_code=400, detail="image payload is empty")

    try:
        source = Image.open(io.BytesIO(payload))
    except Exception as exc:  # pragma: no cover - defensive
        raise HTTPException(status_code=400, detail=f"unable to decode image: {exc}") from exc

    source = source.convert("RGB")
    input_width, input_height = source.size

    processed = apply_preprocess(source, preprocess)
    output_width, output_height = processed.size

    lang = map_locale_to_tesseract(locale)
    lines, confidence = run_tesseract(processed, lang)
    text = "\n".join(lines)
    elapsed_ms = int((time.perf_counter() - start) * 1000)

    return {
        "provider": "python.ocr",
        "confidence": confidence,
        "text": text,
        "lines": lines,
        "meta": {
            "mode": preprocess,
            "input_width": input_width,
            "input_height": input_height,
            "output_width": output_width,
            "output_height": output_height,
            "zone_count": 1,
            "zones": [
                {
                    "x": 0,
                    "y": 0,
                    "w": output_width,
                    "h": output_height,
                    "confidence": confidence,
                    "line_count": len(lines),
                }
            ],
            "duration_ms": elapsed_ms,
        },
        "errors": [],
    }


def map_locale_to_tesseract(locale: str) -> str:
    normalized = (locale or "").strip().lower()
    if normalized.startswith("fr"):
        return "fra"
    if normalized.startswith("de"):
        return "deu"
    return "eng"


def apply_preprocess(image: Image.Image, mode: PreprocessMode) -> Image.Image:
    if mode == "none":
        return image
    if mode == "grayscale":
        return ImageOps.grayscale(image)
    if mode in ("bw", "strong-bw"):
        gray = ImageOps.grayscale(image)
        contrast = ImageEnhance.Contrast(gray).enhance(1.6 if mode == "bw" else 2.2)
        threshold = 170 if mode == "bw" else 150
        return contrast.point(lambda px: 255 if px >= threshold else 0, mode="1").convert("L")
    if mode == "layout-bw":
        return preprocess_layout_bw(image)
    return image


def preprocess_layout_bw(image: Image.Image) -> Image.Image:
    width, height = image.size
    crop_x = int(width * 0.26)
    crop_y = int(height * 0.07)
    crop_w = max(1, width - crop_x)
    crop_h = max(1, int(height * 0.9))
    right_pane = image.crop((crop_x, crop_y, crop_x + crop_w, min(height, crop_y + crop_h)))

    pane_w, pane_h = right_pane.size
    top_offset = int(pane_h * 0.04)
    bottom_offset = int(pane_h * 0.03)
    usable_h = max(1, pane_h - top_offset - bottom_offset)
    base_band_h = max(1, usable_h // 4)
    overlap = int(base_band_h * 0.07)

    bands: list[Image.Image] = []
    for index in range(4):
        band_y = top_offset + (index * base_band_h) - overlap
        band_h = base_band_h + (2 * overlap)
        if index == 3:
            band_h = (pane_h - bottom_offset) - band_y
        band_y = max(0, band_y)
        band_h = min(band_h, pane_h - band_y)
        if band_h > 16:
            bands.append(right_pane.crop((0, band_y, pane_w, band_y + band_h)))

    if not bands:
        bands = [right_pane]

    gap = 12
    target_h = sum(b.height for b in bands) + gap * (len(bands) - 1)
    stacked = Image.new("RGB", (pane_w, target_h), color="white")
    y = 0
    for band in bands:
        stacked.paste(band, (0, y))
        y += band.height + gap

    upscaled = stacked.resize((int(stacked.width * 1.9), int(stacked.height * 1.9)))
    gray = ImageOps.grayscale(upscaled)
    contrast = ImageEnhance.Contrast(gray).enhance(2.4)
    bright = ImageEnhance.Brightness(contrast).enhance(1.05)
    return bright.point(lambda px: 255 if px >= 152 else 0, mode="1").convert("L")


def run_tesseract(image: Image.Image, lang: str) -> tuple[list[str], float]:
    config = "--psm 6"
    data = pytesseract.image_to_data(image, lang=lang, config=config, output_type=pytesseract.Output.DICT)

    raw_lines: list[str] = []
    confidences: list[float] = []
    words = data.get("text", [])
    conf_values = data.get("conf", [])

    for idx, word in enumerate(words):
        text = (word or "").strip()
        if text == "":
            continue
        raw_lines.append(text)
        try:
            conf = float(conf_values[idx])
        except Exception:
            conf = -1.0
        if conf >= 0:
            confidences.append(conf)

    # Rebuild rough lines from OCR text output to stay close to current Symfony expectations.
    text = pytesseract.image_to_string(image, lang=lang, config=config)
    lines = [line.strip() for line in text.splitlines() if line.strip() != ""]
    if not lines and raw_lines:
        lines = raw_lines

    avg_conf = (sum(confidences) / len(confidences)) if confidences else 0.0
    normalized_conf = max(0.0, min(1.0, avg_conf / 100.0))

    return lines, normalized_conf

