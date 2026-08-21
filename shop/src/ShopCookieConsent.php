<?php

declare(strict_types=1);

/**
 * Lightweight cookie consent for the SaaS shop (necessary + optional analytics).
 */
final class ShopCookieConsent
{
    private const COOKIE = 'dg_shop_consent';
    private const DAYS = 365;

    public static function hasDecided(): bool
    {
        return isset($_COOKIE[self::COOKIE]) && $_COOKIE[self::COOKIE] !== '';
    }

    public static function hasAnalytics(): bool
    {
        $raw = $_COOKIE[self::COOKIE] ?? '';
        if ($raw === '') {
            return false;
        }
        $data = json_decode(base64_decode($raw, true) ?: '', true);

        return is_array($data) && !empty($data['analytics']);
    }

    public static function bannerHtml(): string
    {
        if (self::hasDecided()) {
            return '';
        }

        return <<<'HTML'
<div id="shop-cc" class="shop-cc" role="dialog" aria-label="Cookie-Einstellungen">
  <div class="shop-cc__box">
    <h3>Cookies</h3>
    <p>Wir nutzen technisch notwendige Cookies für den Betrieb des Shops (z. B. Sitzung). Statistik-Cookies nur mit Ihrer Zustimmung.
      Details: <a href="/datenschutz">Datenschutz</a>.</p>
    <div class="shop-cc__actions">
      <button type="button" class="shop-btn shop-btn--primary" onclick="shopCc.acceptAll()">Alle akzeptieren</button>
      <button type="button" class="shop-btn shop-btn--ghost" onclick="shopCc.necessaryOnly()">Nur notwendige</button>
    </div>
  </div>
</div>
HTML;
    }

    public static function bannerJs(): string
    {
        $name = self::COOKIE;
        $maxAge = self::DAYS * 24 * 3600;

        return <<<JS
window.shopCc={
  save:function(a){
    var v=btoa(JSON.stringify({necessary:true,analytics:!!a}));
    document.cookie="{$name}="+v+";path=/;max-age={$maxAge};SameSite=Lax";
    var el=document.getElementById("shop-cc"); if(el) el.remove();
  },
  acceptAll:function(){ this.save(true); },
  necessaryOnly:function(){ this.save(false); }
};
JS;
    }

    public static function bannerCss(): string
    {
        return <<<'CSS'
.shop-cc{position:fixed;inset:auto 0 0 0;z-index:1000;padding:1rem;background:rgba(18,16,14,.92);border-top:1px solid rgba(244,240,234,.14)}
.shop-cc__box{width:min(720px,100%);margin:0 auto}
.shop-cc h3{margin:0 0 .4rem;font-family:Georgia,serif;font-weight:500}
.shop-cc p{margin:0 0 .9rem;color:#b7aea3;font-size:.92rem}
.shop-cc__actions{display:flex;flex-wrap:wrap;gap:.5rem}
CSS;
    }
}
