import { startStimulusApp } from '@symfony/stimulus-bundle';
import CompanyFormController from './controllers/company_form_controller.js';
import ClientFormController from './controllers/client_form_controller.js';
import ClientListController from './controllers/client_list_controller.js';
import UserFormController from './controllers/user_form_controller.js';
import UserListController from './controllers/user_list_controller.js';

const app = startStimulusApp();
app.register('company-form', CompanyFormController);
app.register('client-form', ClientFormController);
app.register('client-list', ClientListController);
app.register('user-form', UserFormController);
app.register('user-list', UserListController);
// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);

import ProjectFormController from './controllers/project_form_controller.js';
import ProjectListController from './controllers/project_list_controller.js';
app.register('project-form', ProjectFormController);
app.register('project-list', ProjectListController);
