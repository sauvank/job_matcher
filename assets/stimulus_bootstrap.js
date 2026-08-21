import { startStimulusApp } from '@symfony/stimulus-bundle';
import FileUploadController from './controllers/file_upload_controller.js';

const app = startStimulusApp();
app.register('file-upload', FileUploadController);
