import { startStimulusApp } from '@symfony/stimulus-bundle';
import ApiFeedController from './controllers/api_feed_controller.js';

const app = startStimulusApp();
// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);
app.register('api-feed', ApiFeedController);
