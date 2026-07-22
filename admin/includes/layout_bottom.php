<?php
// admin/includes/layout_bottom.php

$timeoutMs  = 300000;  // 5 minutes
$warningMs  = 240000;  // 4 minutes — show warning
$countdownS = 60;      // 60 second countdown

// Clean absolute URLs using APP_URL from config
$keepAliveUrl = APP_URL . '/admin/keep_alive.php';
$logoutUrl    = APP_URL . '/admin/logout.php';
?>
    </div><!-- /content -->
</div><!-- /main -->

<!-- ══════════════════════════════════════════════
     SESSION TIMEOUT WARNING OVERLAY
══════════════════════════════════════════════ -->
<div id="timeout-overlay"
     style="display:none; position:fixed; inset:0;
            background:rgba(0,0,0,0.55); z-index:9999;
            align-items:center; justify-content:center;">

    <div style="background:#fff; border-radius:16px; padding:40px 36px;
                max-width:420px; width:90%; text-align:center;
                box-shadow:0 20px 60px rgba(0,0,0,0.3);">

        <div style="font-size:3rem; margin-bottom:12px;">⏱️</div>

        <h2 style="color:#003f87; margin-bottom:10px; font-size:1.3rem;">
            Session Expiring Soon
        </h2>

        <p style="color:#555; font-size:0.95rem; line-height:1.6; margin-bottom:20px;">
            You have been inactive for a while.<br>
            You will be automatically logged out in
        </p>

        <div id="countdown-display"
             style="font-size:3rem; font-weight:800; color:#c0392b;
                    margin-bottom:24px; line-height:1;">
            60
        </div>
        <p style="color:#888; font-size:0.82rem; margin-bottom:24px;">seconds</p>

        <!-- Progress bar -->
        <div style="background:#f0f0f0; border-radius:20px;
                    height:8px; margin-bottom:28px; overflow:hidden;">
            <div id="timeout-progress"
                 style="height:100%; width:100%; background:#f7a800;
                        border-radius:20px; transition:width 1s linear;">
            </div>
        </div>

        <!-- Buttons -->
        <div style="display:flex; gap:12px; justify-content:center;">
            <button onclick="keepAlive()"
                    style="padding:12px 28px; background:#003f87; color:#fff;
                           border:none; border-radius:8px; font-size:1rem;
                           font-weight:700; cursor:pointer;">
                ✅ Stay Logged In
            </button>
            <button onclick="logoutNow()"
                    style="padding:12px 20px; background:#f0f4f8; color:#555;
                           border:1.5px solid #dee2e6; border-radius:8px;
                           font-size:1rem; cursor:pointer;">
                🚪 Logout
            </button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════
     SESSION TIMEOUT JAVASCRIPT
══════════════════════════════════════════════ -->
<script>
(function () {
    const TIMEOUT_MS  = <?=  $timeoutMs ?>;
    const WARNING_MS  = <?=  $warningMs ?>;
    const COUNTDOWN_S = <?=  $countdownS ?>;

    // Absolute URLs — no relative path issues
    const KEEP_ALIVE_URL = '<?=  $keepAliveUrl ?>';
    const LOGOUT_URL     = '<?=  $logoutUrl ?>';

    let warningTimer      = null;
    let logoutTimer       = null;
    let countdownInterval = null;
    let countdownLeft     = COUNTDOWN_S;

    const overlay      = document.getElementById('timeout-overlay');
    const countdownEl  = document.getElementById('countdown-display');
    const progressEl   = document.getElementById('timeout-progress');

    // ── Show warning overlay ──────────────────────────────────
    function showWarning() {
        countdownLeft = COUNTDOWN_S;
        overlay.style.display = 'flex';
        updateCountdown();

        countdownInterval = setInterval(function () {
            countdownLeft--;
            updateCountdown();
            if (countdownLeft <= 0) {
                clearInterval(countdownInterval);
                logoutNow();
            }
        }, 1000);
    }

    function updateCountdown() {
        countdownEl.textContent = countdownLeft;
        var pct = (countdownLeft / COUNTDOWN_S) * 100;
        progressEl.style.width = pct + '%';
        progressEl.style.background = countdownLeft > 30 ? '#f7a800' : '#c0392b';
    }

    // ── Keep alive — ping server + reset timers ───────────────
    window.keepAlive = function () {
        fetch(KEEP_ALIVE_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.status === 'ok') {
                overlay.style.display = 'none';
                clearInterval(countdownInterval);
                resetTimers();
            } else {
                logoutNow();
            }
        })
        .catch(function () {
            // Network error — still reset to avoid false logout
            overlay.style.display = 'none';
            clearInterval(countdownInterval);
            resetTimers();
        });
    };

    // ── Logout ────────────────────────────────────────────────
    window.logoutNow = function () {
        window.location.href = LOGOUT_URL;
    };

    // ── Reset inactivity timers ───────────────────────────────
    function resetTimers() {
        clearTimeout(warningTimer);
        clearTimeout(logoutTimer);
        warningTimer = setTimeout(showWarning, WARNING_MS);
        logoutTimer  = setTimeout(logoutNow,   TIMEOUT_MS);
    }

    // ── Track user activity ───────────────────────────────────
    ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll', 'click']
        .forEach(function (evt) {
            document.addEventListener(evt, resetTimers, { passive: true });
        });

    // ── Start on page load ────────────────────────────────────
    resetTimers();

})();
</script>

</body>
</html>
