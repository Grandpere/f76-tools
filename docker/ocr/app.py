from __future__ import annotations

import os
from functools import lru_cache
from pathlib import Path
from typing import List

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
import easyocr


class OcrRequest(BaseModel):
    image_path: str
    locale: str = "en"


class OcrResponse(BaseModel):
    provider: str
    confidence: float
    text: str
    lines: List[str]


app = FastAPI(title="f76-ocr")

ALLOWED_PREFIX = os.environ.get("OCR_ALLOWED_PREFIX", "/var/www/html/data")


@lru_cache(maxsize=6)
def get_reader(lang_key: str) -> easyocr.Reader:
    langs = lang_key.split(",")
    return easyocr.Reader(langs, gpu=False)


def locale_to_langs(locale: str) -> list[str]:
    normalized = locale.strip().lower()
    if normalized.startswith("fr"):
        return ["fr", "en"]
    if normalized.startswith("de"):
        return ["de", "en"]
    return ["en"]


def ensure_allowed_path(image_path: str) -> Path:
    path = Path(image_path).resolve()
    allowed = Path(ALLOWED_PREFIX).resolve()

    try:
        path.relative_to(allowed)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=f"image path not allowed: {path}") from exc

    if not path.is_file():
        raise HTTPException(status_code=404, detail=f"image not found: {path}")

    return path


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/ocr", response_model=OcrResponse)
def ocr_scan(payload: OcrRequest) -> OcrResponse:
    path = ensure_allowed_path(payload.image_path)
    langs = locale_to_langs(payload.locale)

    try:
        reader = get_reader(",".join(langs))
        raw = reader.readtext(str(path), detail=1, paragraph=False)
    except Exception as exc:  # noqa: BLE001
        raise HTTPException(status_code=500, detail=f"easyocr failed: {exc}") from exc

    lines: list[str] = []
    conf_sum = 0.0
    conf_count = 0

    for row in raw:
        if not isinstance(row, (list, tuple)) or len(row) < 3:
            continue

        text = str(row[1]).strip()
        if text:
            lines.append(text)

        try:
            confidence = float(row[2])
        except Exception:  # noqa: BLE001
            continue

        if confidence >= 0.0:
            conf_sum += confidence
            conf_count += 1

    if conf_count > 0:
        avg_conf = conf_sum / conf_count
    else:
        avg_conf = 0.0

    return OcrResponse(
        provider="easyocr",
        confidence=max(0.0, min(1.0, avg_conf)),
        text="\n".join(lines),
        lines=lines,
    )
