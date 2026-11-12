<?php
declare(strict_types=1);

/**
 * =============================================================================
 * ANTIDESIGNER CORE - MOCK MEDIA TOOL (0 ₽ Local Testing)
 * Synthesizes test WAV audio, SVG posters, and 5-second Canvas motion code
 * without making external API calls to Polza.ai or Gemini.
 * =============================================================================
 */

if (!function_exists('generateMockWavFile')) {
    /**
     * Synthesize a clean 16-bit PCM WAV audio file with dual-harmonic chime
     * (440 Hz + 660 Hz with acoustic decay) entirely in pure PHP.
     */
    function generateMockWavFile(string $filePath, float $durationSeconds = 2.5): string {
        $sampleRate = 22050; // Standard 22.05 kHz sample rate
        $numSamples = (int)($sampleRate * $durationSeconds);
        $numChannels = 1; // Mono
        $bytesPerSample = 2; // 16-bit PCM
        $byteRate = $sampleRate * $numChannels * $bytesPerSample;
        $blockAlign = $numChannels * $bytesPerSample;
        $dataSize = $numSamples * $numChannels * $bytesPerSample;
        $fileSize = 36 + $dataSize;

        // RIFF header
        $header = 'RIFF' . pack('V', $fileSize) . 'WAVE';
        // 'fmt ' chunk
        $header .= 'fmt ' . pack('V', 16) . pack('v', 1) . pack('v', $numChannels)
            . pack('V', $sampleRate) . pack('V', $byteRate) . pack('v', $blockAlign) . pack('v', 16);
        // 'data' chunk
        $header .= 'data' . pack('V', $dataSize);

        $samples = '';
        for ($i = 0; $i < $numSamples; $i++) {
            $t = $i / $sampleRate;
            // Harmonic dual tone (A4 440Hz + E5 660Hz) with natural exponential decay
            $envelope = exp(-1.5 * $t);
            $val = (sin(2 * M_PI * 440 * $t) * 0.65 + sin(2 * M_PI * 660 * $t) * 0.35) * $envelope;
            $intSample = (int)round($val * 24000);
            if ($intSample > 32767) $intSample = 32767;
            if ($intSample < -32768) $intSample = -32768;
            $samples .= pack('v', $intSample < 0 ? $intSample + 65536 : $intSample);
        }

        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($filePath, $header . $samples);
        return $filePath;
    }
}

if (!function_exists('generateMockSvgFile')) {
    /**
     * Generate a 9:16 vertical vector poster for scene mock b-roll testing.
     */
    function generateMockSvgFile(string $filePath, int $sceneNum = 1): string {
        $svgContent = '<svg xmlns="http://www.w3.org/2000/svg" width="1080" height="1920" viewBox="0 0 1080 1920">
  <rect width="1080" height="1920" fill="#08090c"/>
  <defs>
    <radialGradient id="glow" cx="50%" cy="50%" r="50%">
      <stop offset="0%" stop-color="#00ff88" stop-opacity="0.15"/>
      <stop offset="100%" stop-color="#08090c" stop-opacity="0"/>
    </radialGradient>
  </defs>
  <rect width="1080" height="1920" fill="url(#glow)"/>
  <rect x="80" y="80" width="920" height="1760" rx="32" fill="none" stroke="#2d333b" stroke-width="4"/>
  <circle cx="540" cy="760" r="220" fill="none" stroke="#00ff88" stroke-width="12" stroke-dasharray="16 8"/>
  <text x="540" y="730" font-family="-apple-system, sans-serif" font-size="80" font-weight="900" fill="#ffffff" text-anchor="middle">SCENE #' . $sceneNum . '</text>
  <text x="540" y="810" font-family="-apple-system, sans-serif" font-size="34" font-weight="700" fill="#00ff88" text-anchor="middle">B-ROLL MOCK POSTER</text>
  <rect x="240" y="1120" width="600" height="80" rx="40" fill="rgba(0, 255, 136, 0.12)" stroke="#00ff88" stroke-width="2"/>
  <text x="540" y="1170" font-family="-apple-system, sans-serif" font-size="30" font-weight="bold" fill="#00ff88" text-anchor="middle">TEST ASSET (0 ₽)</text>
  <text x="540" y="1400" font-family="monospace" font-size="28" fill="#8b949e" text-anchor="middle">1080 x 1920 · 9:16 VERTICAL</text>
</svg>';
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($filePath, $svgContent);
        return $filePath;
    }
}

if (!function_exists('generateDemoReelCanvasHtml')) {
    /**
     * Generate the complete 5-second 60fps Dark Tech Canvas animation
     * on <canvas id="reel-canvas" width="1080" height="1920"> with window.startReelAnimation()
     * and window.REEL_DURATION_MS = 5000.
     */
    function generateDemoReelCanvasHtml(int $durationMs = 5000): string {
        return '<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
<title>Demo Reel - AntiDesigner 0 ₽ Test</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html, body {
    width: 100%; height: 100%;
    background: #08090c;
    overflow: hidden;
    display: flex; align-items: center; justify-content: center;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  }
  #reel-canvas {
    width: 100%; height: 100%;
    max-height: 100%;
    object-fit: contain;
    background: #08090c;
    display: block;
  }
</style>
</head>
<body>
<canvas id="reel-canvas" width="1080" height="1920"></canvas>
<script>
window.REEL_DURATION_MS = ' . $durationMs . ';
const canvas = document.getElementById("reel-canvas");
const ctx = canvas.getContext("2d");

let startTime = null;
let animId = null;

window.startReelAnimation = function() {
  if (animId) cancelAnimationFrame(animId);
  startTime = performance.now();
  render(performance.now());
};

function render(now) {
  if (!startTime) startTime = now;
  let elapsed = now - startTime;
  if (elapsed > window.REEL_DURATION_MS) elapsed = window.REEL_DURATION_MS;
  const progress = elapsed / window.REEL_DURATION_MS;

  // 1. Темный фон Dark Tech
  ctx.fillStyle = "#08090c";
  ctx.fillRect(0, 0, 1080, 1920);

  // 2. Сетка
  ctx.strokeStyle = "rgba(255, 255, 255, 0.04)";
  ctx.lineWidth = 1.5;
  for (let x = 0; x < 1080; x += 90) {
    ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, 1920); ctx.stroke();
  }
  for (let y = 0; y < 1920; y += 90) {
    ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(1080, y); ctx.stroke();
  }

  // Пульсирующее неоновое пятно в центре
  const pulse = Math.sin(progress * Math.PI * 4) * 0.5 + 0.5;
  const radial = ctx.createRadialGradient(540, 750, 40, 540, 750, 600);
  radial.addColorStop(0, "rgba(0, 255, 136, " + (0.08 + pulse * 0.08) + ")");
  radial.addColorStop(1, "rgba(8, 9, 12, 0)");
  ctx.fillStyle = radial;
  ctx.fillRect(0, 0, 1080, 1920);

  // 3. Верхний бейдж
  ctx.save();
  ctx.fillStyle = "rgba(0, 255, 136, 0.12)";
  ctx.strokeStyle = "#00ff88";
  ctx.lineWidth = 2;
  ctx.beginPath();
  if (ctx.roundRect) ctx.roundRect(140, 140, 800, 96, 48);
  else ctx.rect(140, 140, 800, 96);
  ctx.fill(); ctx.stroke();

  ctx.fillStyle = "#00ff88";
  ctx.font = "bold 38px -apple-system, sans-serif";
  ctx.textAlign = "center";
  ctx.textBaseline = "middle";
  ctx.fillText("⚡ ТЕСТОВЫЙ ДЕМО-РИЛС (0 ₽)", 540, 188);
  ctx.restore();

  // 4. Спидометр скорости 100/100
  ctx.save();
  const gaugeAngle = Math.min(progress * 2.0, 1) * Math.PI * 2;
  ctx.lineWidth = 22;
  ctx.strokeStyle = "rgba(255, 255, 255, 0.08)";
  ctx.beginPath();
  ctx.arc(540, 550, 190, 0, Math.PI * 2);
  ctx.stroke();

  ctx.strokeStyle = "#00ff88";
  ctx.shadowColor = "#00ff88";
  ctx.shadowBlur = 18;
  ctx.beginPath();
  ctx.arc(540, 550, 190, -Math.PI / 2, -Math.PI / 2 + gaugeAngle);
  ctx.stroke();
  ctx.shadowBlur = 0;

  ctx.fillStyle = "#ffffff";
  ctx.font = "900 100px -apple-system, sans-serif";
  ctx.textAlign = "center";
  ctx.textBaseline = "middle";
  const speed = Math.min(100, Math.floor(progress * 160));
  ctx.fillText(speed >= 100 ? "100" : speed + "", 540, 530);

  ctx.fillStyle = "#00ff88";
  ctx.font = "bold 32px -apple-system, sans-serif";
  ctx.fillText("0.35s TTFB / FAST LOAD", 540, 605);
  ctx.restore();

  // 5. Заголовок и динамические субтитры
  ctx.save();
  ctx.fillStyle = "#ffffff";
  ctx.font = "bold 56px -apple-system, sans-serif";
  ctx.textAlign = "center";
  ctx.textBaseline = "top";
  ctx.fillText("ТЕСТ СИНХРОНИЗАЦИИ", 540, 830);

  // Карточка сцены
  ctx.fillStyle = "rgba(18, 22, 31, 0.9)";
  ctx.strokeStyle = "rgba(0, 255, 136, 0.4)";
  ctx.lineWidth = 2;
  ctx.beginPath();
  if (ctx.roundRect) ctx.roundRect(80, 1020, 920, 480, 32);
  else ctx.rect(80, 1020, 920, 480);
  ctx.fill(); ctx.stroke();

  const sec = Math.min(5, Math.floor(elapsed / 1000) + 1);
  ctx.fillStyle = "#00ff88";
  ctx.font = "bold 34px -apple-system, sans-serif";
  ctx.textAlign = "left";
  ctx.fillText("Фаза удержания #" + sec + " · " + (sec - 1) + "-5 сек", 130, 1100);

  ctx.fillStyle = "#ffffff";
  ctx.font = "bold 48px -apple-system, sans-serif";
  const phrases = [
    "Секунда 1: Мгновенный хук внимания",
    "Секунда 2: Чистый код без конструкторов",
    "Секунда 3: Замер скорости PageSpeed",
    "Секунда 4: 0% нагрузки на процессор",
    "Секунда 5: Ролик и звук синхронизированы!"
  ];
  ctx.fillText(phrases[sec - 1] || phrases[4], 130, 1180);

  ctx.fillStyle = "#8b949e";
  ctx.font = "32px -apple-system, sans-serif";
  ctx.fillText("Web MediaRecorder + Canvas.captureStream(30)", 130, 1260);

  ctx.fillStyle = "rgba(0, 255, 136, 0.15)";
  ctx.strokeStyle = "#00ff88";
  ctx.lineWidth = 1;
  ctx.beginPath();
  if (ctx.roundRect) ctx.roundRect(130, 1340, 420, 56, 28);
  ctx.fill(); ctx.stroke();
  ctx.fillStyle = "#00ff88";
  ctx.font = "bold 26px -apple-system, sans-serif";
  ctx.fillText("⚡ ZERO-CPU CLIENT EXPORT", 160, 1378);
  ctx.restore();

  // 6. Нижний таймлайн 0-5 сек
  ctx.save();
  ctx.fillStyle = "rgba(255, 255, 255, 0.1)";
  ctx.fillRect(80, 1780, 920, 18);
  ctx.fillStyle = "#00ff88";
  ctx.shadowColor = "#00ff88";
  ctx.shadowBlur = 10;
  ctx.fillRect(80, 1780, 920 * progress, 18);
  ctx.shadowBlur = 0;

  const curSec = (elapsed / 1000).toFixed(1);
  ctx.fillStyle = "#8b949e";
  ctx.font = "32px monospace";
  ctx.textAlign = "right";
  ctx.fillText(curSec + "s / 5.0s", 1000, 1750);
  ctx.restore();

  if (elapsed < window.REEL_DURATION_MS) {
    animId = requestAnimationFrame(render);
  }
}

window.addEventListener("load", () => {
  window.startReelAnimation();
});
</script>
</body>
</html>';
    }
}
