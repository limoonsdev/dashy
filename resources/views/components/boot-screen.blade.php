<div id="dashy-boot" aria-hidden="true">
    <div class="dashy-boot__inner">
        <pre class="dashy-boot__logo">██████╗  █████╗ ███████╗██╗  ██╗██╗   ██╗
██╔══██╗██╔══██╗██╔════╝██║  ██║╚██╗ ██╔╝
██║  ██║███████║███████╗███████║ ╚████╔╝
██║  ██║██╔══██║╚════██║██╔══██║  ╚██╔╝
██████╔╝██║  ██║███████║██║  ██║   ██║
╚═════╝ ╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝   ╚═╝</pre>
        <p class="dashy-boot__tagline">self-hosted control plane</p>
        <div class="dashy-boot__bar"><span></span></div>
    </div>
</div>

<style>
    #dashy-boot {
        position: fixed;
        inset: 0;
        z-index: 9999;
        display: grid;
        place-items: center;
        background: #08070c;
        transition: opacity 420ms ease, visibility 420ms ease;
    }

    #dashy-boot.is-done {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }

    .dashy-boot__inner {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 18px;
        padding: 24px;
    }

    .dashy-boot__logo {
        margin: 0;
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: clamp(6px, 1.7vw, 14px);
        line-height: 1.15;
        letter-spacing: 0;
        background: linear-gradient(90deg, #8b5cf6, #22d3ee 55%, #8b5cf6);
        background-size: 220% 100%;
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        animation: dashy-boot-shimmer 2.4s linear infinite;
    }

    .dashy-boot__tagline {
        margin: 0;
        font-size: 11px;
        letter-spacing: 0.32em;
        text-transform: uppercase;
        color: #6b7280;
    }

    .dashy-boot__bar {
        width: min(240px, 60vw);
        height: 2px;
        overflow: hidden;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.08);
    }

    .dashy-boot__bar span {
        display: block;
        width: 40%;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #8b5cf6, #22d3ee);
        animation: dashy-boot-load 1.1s ease-in-out infinite;
    }

    @keyframes dashy-boot-shimmer {
        to { background-position: -220% 0; }
    }

    @keyframes dashy-boot-load {
        0% { transform: translateX(-110%); }
        100% { transform: translateX(360%); }
    }

    @media (prefers-reduced-motion: reduce) {
        .dashy-boot__logo,
        .dashy-boot__bar span {
            animation: none;
        }
    }
</style>

<script>
    (function () {
        var boot = document.getElementById('dashy-boot');
        if (!boot) return;

        var key = 'dashy:booted';

        try {
            if (sessionStorage.getItem(key)) {
                boot.remove();
                return;
            }
            sessionStorage.setItem(key, '1');
        } catch (e) {
            // Private mode: show it once and move on.
        }

        var done = false;
        function finish() {
            if (done) return;
            done = true;
            boot.classList.add('is-done');
            setTimeout(function () { boot.remove(); }, 460);
        }

        if (document.readyState === 'complete') {
            setTimeout(finish, 380);
        } else {
            window.addEventListener('load', function () { setTimeout(finish, 380); });
        }

        setTimeout(finish, 2600);
    })();
</script>
