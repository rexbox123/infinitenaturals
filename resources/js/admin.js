import 'trix';
import Alpine from 'alpinejs';

// Block file attachments in the rich-text editor.
document.addEventListener('trix-file-accept', (event) => event.preventDefault());

window.Alpine = Alpine;
Alpine.start();
