import Alpine from 'alpinejs';
import quickProgram from './quick-program';
Alpine.data('quickProgram', quickProgram);
import websiteTracking from './website-tracking';
Alpine.data('websiteTracking', websiteTracking);
window.Alpine = Alpine;
Alpine.start();
