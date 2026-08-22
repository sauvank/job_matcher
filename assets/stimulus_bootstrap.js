import { startStimulusApp } from '@symfony/stimulus-bundle';
import FileUploadController from './controllers/file_upload_controller.js';
import SkillFilterController from './controllers/skill_filter_controller.js';

const app = startStimulusApp();
app.register('file-upload', FileUploadController);
app.register('skill-filter', SkillFilterController);
