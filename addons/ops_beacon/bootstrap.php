<?php

if (!defined('BNT_ADDON_RUNTIME')) {
    return;
}

bnt_addon_register_hook(
    'page_head',
    static function (): string {
        return <<<HTML
<style>
.addon-ops-beacon {
  width: min(1100px, calc(100% - 24px));
  margin: 10px auto 14px;
  border: 1px solid rgba(0, 238, 255, 0.22);
  background: linear-gradient(180deg, rgba(8, 25, 40, 0.96), rgba(4, 11, 20, 0.98));
  color: #d7f7ff;
  box-shadow: 0 0 18px rgba(0, 238, 255, 0.08);
}
.addon-ops-beacon__inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 10px 14px;
}
.addon-ops-beacon__tag {
  font-size: 11px;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: #7edfff;
  font-weight: 700;
}
.addon-ops-beacon__copy {
  font-size: 13px;
  color: #eefbff;
}
</style>
HTML;
    }
);

bnt_addon_register_hook(
    'page_top',
    static function (): string {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        if (empty($_SESSION['logged_in'])) {
            return '';
        }

        return <<<HTML
<section class="addon-ops-beacon" aria-label="Operations beacon">
  <div class="addon-ops-beacon__inner">
    <div class="addon-ops-beacon__tag">Ops Beacon</div>
    <div class="addon-ops-beacon__copy">Addon runtime is active. This banner is coming from <code>addons/ops_beacon</code>.</div>
  </div>
</section>
HTML;
    }
);
