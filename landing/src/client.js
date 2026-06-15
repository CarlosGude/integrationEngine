export const JS = `
function copyInstall(el) {
  navigator.clipboard.writeText('composer require carlosgude/integration-engine').then(function() {
    el.classList.add('copied');
    setTimeout(function() { el.classList.remove('copied'); }, 1800);
  });
}`;
