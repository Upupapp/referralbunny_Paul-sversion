import Alpine from 'alpinejs';
import quickProgram from './quick-program';
Alpine.data('quickProgram', quickProgram);
import websiteTracking from './website-tracking';
Alpine.data('websiteTracking', websiteTracking);
import gethiredConnection from './gethired-connection';
Alpine.data('gethiredConnection', gethiredConnection);
window.Alpine = Alpine;
Alpine.start();
