export const JS = `
function copyInstall(el) {
  navigator.clipboard.writeText('composer require carlosgude/integration-engine').then(function() {
    el.classList.add('copied');
    setTimeout(function() { el.classList.remove('copied'); }, 1800);
  });
}

async function runDemo() {
  var btn = document.getElementById('run-btn');
  btn.textContent = '⏳ Running...';
  btn.disabled = true;

  var tradOut   = document.getElementById('trad-output');
  var engOut    = document.getElementById('eng-output');
  var tradTimer = document.getElementById('trad-timer');
  var engTimer  = document.getElementById('eng-timer');

  tradOut.innerHTML  = '<div class="out-loading">Loading…</div>';
  engOut.innerHTML   = '<div class="out-loading">Loading…</div>';
  tradTimer.className = 'timer-val idle';
  tradTimer.textContent = '…';
  engTimer.className  = 'timer-val idle';
  engTimer.textContent  = '…';

  var ids = 'de/2513,ch/8500010,fr/8711300';

  function timeIt(url) {
    var t0 = Date.now();
    return fetch(url)
      .then(function(r) { return r.json(); })
      .then(function(data) { return { data: data, ms: Date.now() - t0 }; });
  }

  Promise.all([
    timeIt('/traditional/stations/batch?ids=' + ids),
    timeIt('/engine/stations/batch?ids='      + ids),
  ]).then(function(results) {
    var trad = results[0];
    var eng  = results[1];

    tradOut.innerHTML   = renderStations(trad.data);
    tradTimer.className = 'timer-val bad';
    tradTimer.textContent = trad.ms + 'ms';

    engOut.innerHTML   = renderStations(eng.data);
    engTimer.className = 'timer-val good';
    engTimer.textContent = eng.ms + 'ms';

    btn.textContent = '▶ Run again';
    btn.disabled = false;
  }).catch(function(e) {
    var err = '<div style="color:#f87171;padding:1rem;font-size:.74rem">Error: ' + e.message + '</div>';
    tradOut.innerHTML = err;
    engOut.innerHTML  = err;
    btn.textContent = '▶ Run again';
    btn.disabled = false;
  });
}

function renderStations(data) {
  return Object.entries(data).map(function(entry) {
    var key = entry[0]; var s = entry[1];
    var photo = s.has_photo ? '📷 with photo' : 'no photo';
    var lat   = s.lat != null ? parseFloat(s.lat).toFixed(3) : '?';
    var lon   = s.lon != null ? parseFloat(s.lon).toFixed(3) : '?';
    return '<div class="out-station">'
      + '<div class="out-key">' + key + '</div>'
      + '<div class="out-title">' + s.title + '</div>'
      + '<div class="out-meta">' + s.country.toUpperCase() + ' \xB7 ' + lat + ', ' + lon + ' \xB7 ' + photo + '</div>'
      + '</div>';
  }).join('');
}`;
