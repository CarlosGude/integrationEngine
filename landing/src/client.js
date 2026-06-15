export const JS = `
function copyInstall(el) {
  navigator.clipboard.writeText('composer require carlosgude/integration-engine').then(function() {
    el.classList.add('copied');
    setTimeout(function() { el.classList.remove('copied'); }, 1800);
  });
}

function togglePattern() {
  var body   = document.getElementById('patron-collapsible');
  var btn    = document.getElementById('pattern-expand-btn');
  var label  = document.getElementById('peb-label');
  var open   = body.classList.toggle('open');
  btn.classList.toggle('open', open);
  label.textContent = open ? btn.dataset.labelClose : btn.dataset.labelOpen;
}

function toggleNav() {
  document.getElementById('nav-mobile-menu').classList.toggle('open');
}

function openAndScrollToPattern() {
  var body   = document.getElementById('patron-collapsible');
  var wasOpen = body.classList.contains('open');
  if (!wasOpen) togglePattern();
  setTimeout(function() {
    document.getElementById('pattern').scrollIntoView({ behavior: 'smooth', block: 'start' });
  }, wasOpen ? 0 : 80);
}`;
