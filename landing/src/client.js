export const JS = `
function copyInstall(el) {
  navigator.clipboard.writeText('composer require carlosgude/integration-engine').then(() => {
    el.classList.add('copied');
    setTimeout(() => el.classList.remove('copied'), 2000);
  });
}

function showTab(btn, panelId) {
  const block = btn.closest('.code-block');
  block.querySelectorAll('.code-tab').forEach(t => t.classList.remove('active'));
  block.querySelectorAll('.code-panel').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById(panelId).classList.add('active');
  renderLineNumbers(block);
}

function renderLineNumbers(block) {
  const activePanel = block.querySelector('.code-panel.active pre');
  if (!activePanel) return;
  const lines = activePanel.textContent.split('\\n');
  if (lines[lines.length - 1] === '') lines.pop();
  const gutter = block.querySelector('.line-numbers');
  if (!gutter) return;
  gutter.innerHTML = lines.map((_, i) => '<span>' + (i + 1) + '</span>').join('');
}

function initPipeline() {
  const labels = Array.from(document.querySelectorAll('.pipe-label'));
  const arrows = Array.from(document.querySelectorAll('.pipe-arrow'));
  const total  = labels.length;
  let current  = 0;
  function step() {
    labels.forEach(l => l.classList.remove('pipe-active'));
    arrows.forEach(a => a.classList.remove('pipe-arrow-active'));
    labels[current].classList.add('pipe-active');
    if (current > 0) arrows[current - 1].classList.add('pipe-arrow-active');
    current = (current + 1) % total;
  }
  step();
  setInterval(step, 650);
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.code-block').forEach(block => renderLineNumbers(block));
  initPipeline();
});`;
