import { startStimulusApp } from '@symfony/stimulus-bundle';
import ApiFeedController from './controllers/api_feed_controller.js';
import ItemCatalogController from './controllers/item_catalog_controller.js';
import MinervaCountdownController from './controllers/minerva_countdown_controller.js';
import MinervaKnowledgeController from './controllers/minerva_knowledge_controller.js';
import MinervaProgressionController from './controllers/minerva_progression_controller.js';
import PlayerProgressionController from './controllers/player_progression_controller.js';
import TextareaAutosizeController from './controllers/textarea_autosize_controller.js';

const app = startStimulusApp();
// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);
app.register('api-feed', ApiFeedController);
app.register('item-catalog', ItemCatalogController);
app.register('minerva-countdown', MinervaCountdownController);
app.register('minerva-knowledge', MinervaKnowledgeController);
app.register('minerva-progression', MinervaProgressionController);
app.register('player-progression', PlayerProgressionController);
app.register('textarea-autosize', TextareaAutosizeController);
