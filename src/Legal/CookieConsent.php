<?php
declare(strict_types=1);

/**
 * Cookie consent management (§ 25 TDDDG / DSGVO).
 *
 * - Renders a consent banner with accept/reject/customize options.
 * - Stores consent state in a first-party cookie (no external service).
 * - Provides server-side helpers to check which categories are consented.
 * - Results are referenced in the Datenschutzerklärung.
 */
final class CookieConsent
{
    private const COOKIE_NAME = 'dg_cookie_consent';
    private const COOKIE_LIFETIME = 365 * 24 * 3600; // 1 year

    /** Standard cookie categories per TDDDG/DSGVO. */
    public const CATEGORIES = [
        'necessary'  => ['label' => 'Technisch notwendig', 'required' => true,  'description' => 'Diese Cookies sind für den Betrieb der Website erforderlich und können nicht deaktiviert werden.'],
        'functional' => ['label' => 'Funktional',          'required' => false, 'description' => 'Ermöglichen erweiterte Funktionen wie Sprach-Einstellungen oder personalisierte Inhalte.'],
        'analytics'  => ['label' => 'Statistik',           'required' => false, 'description' => 'Helfen uns zu verstehen, wie Besucher die Website nutzen (z.B. Seitenaufrufe, Verweildauer).'],
        'marketing'  => ['label' => 'Marketing',           'required' => false, 'description' => 'Werden genutzt, um Werbung relevanter zu gestalten und Kampagnen zu messen.'],
    ];

    /** @return array<string, bool> Current consent state (category => allowed). */
    public static function state(): array
    {
        $cookie = $_COOKIE[self::COOKIE_NAME] ?? '';
        if ($cookie === '') {
            return ['necessary' => true, 'functional' => false, 'analytics' => false, 'marketing' => false];
        }

        $data = json_decode(base64_decode($cookie, true) ?: '', true);
        if (!is_array($data)) {
            return ['necessary' => true, 'functional' => false, 'analytics' => false, 'marketing' => false];
        }

        $state = ['necessary' => true];
        foreach (self::CATEGORIES as $key => $cat) {
            if ($cat['required']) {
                $state[$key] = true;
            } else {
                $state[$key] = !empty($data[$key]);
            }
        }
        return $state;
    }

    /**
     * @param string $category One of necessary, functional, analytics, marketing.
     */
    public static function hasConsent(string $category): bool
    {
        return self::state()[$category] ?? false;
    }

    /** Whether the visitor has already saved a consent choice. */
    public static function hasDecided(): bool
    {
        return isset($_COOKIE[self::COOKIE_NAME]) && $_COOKIE[self::COOKIE_NAME] !== '';
    }

    /** HTML for the consent banner – inject in the layout before </body>. */
    public static function bannerHtml(): string
    {
        if (self::hasDecided()) {
            return '';
        }

        $categories = '';
        foreach (self::CATEGORIES as $key => $cat) {
            $checked = $cat['required'] ? 'checked disabled' : '';
            $categories .= '<label class="dg-cc-category">'
                . '<input type="checkbox" name="' . $key . '" value="1" ' . $checked . '>'
                . '<span class="dg-cc-cat-label">' . htmlspecialchars($cat['label']) . '</span>'
                . '<span class="dg-cc-cat-desc">' . htmlspecialchars($cat['description']) . '</span>'
                . '</label>';
        }

        return <<<HTML
<div id="dg-cookie-consent" class="dg-cc-overlay" role="dialog" aria-label="Cookie-Einstellungen">
  <div class="dg-cc-banner">
    <div class="dg-cc-text">
      <h3>Cookie-Einstellungen</h3>
      <p>Wir verwenden Cookies, um Ihnen die bestmögliche Nutzung unserer Website zu ermöglichen.
         Technisch notwendige Cookies werden immer gesetzt. Weitere Cookies setzen wir nur mit Ihrer Einwilligung.
         <a href="/datenschutz">Mehr erfahren</a></p>
    </div>
    <div class="dg-cc-details" id="dg-cc-details" style="display:none">
      {$categories}
    </div>
    <div class="dg-cc-actions">
      <button type="button" class="dg-cc-btn dg-cc-btn--accept" onclick="dgCookieConsent.acceptAll()">Alle akzeptieren</button>
      <button type="button" class="dg-cc-btn dg-cc-btn--reject" onclick="dgCookieConsent.rejectAll()">Nur notwendige</button>
      <button type="button" class="dg-cc-btn dg-cc-btn--settings" onclick="dgCookieConsent.toggleDetails()">Einstellungen</button>
      <button type="button" class="dg-cc-btn dg-cc-btn--save" id="dg-cc-save" style="display:none" onclick="dgCookieConsent.saveCustom()">Auswahl speichern</button>
    </div>
  </div>
</div>
HTML;
    }

    /** Inline-CSS für das Cookie-Banner. */
    public static function bannerCss(): string
    {
        return <<<CSS
.dg-cc-overlay{position:fixed;bottom:0;left:0;right:0;z-index:99999;background:rgba(0,0,0,.4);padding:1rem}
.dg-cc-banner{max-width:680px;margin:0 auto;background:#fff;border-radius:12px;box-shadow:0 -2px 20px rgba(0,0,0,.15);padding:1.5rem;font-family:system-ui,-apple-system,sans-serif;color:#1e293b;font-size:.9rem;line-height:1.5}
.dg-cc-banner h3{font-size:1.1rem;margin:0 0 .5rem}
.dg-cc-banner p{margin:0 0 1rem;color:#475569}
.dg-cc-banner a{color:#2563eb}
.dg-cc-details{border-top:1px solid #e2e8f0;padding-top:.75rem;margin-bottom:.75rem}
.dg-cc-category{display:flex;flex-wrap:wrap;align-items:center;gap:.4rem;padding:.5rem 0;border-bottom:1px solid #f1f5f9;cursor:pointer}
.dg-cc-category:last-child{border-bottom:none}
.dg-cc-cat-label{font-weight:600;font-size:.875rem;flex:0 0 auto}
.dg-cc-cat-desc{flex:1 1 100%;font-size:.8rem;color:#64748b;margin-top:.15rem}
.dg-cc-actions{display:flex;flex-wrap:wrap;gap:.5rem}
.dg-cc-btn{padding:.5rem 1rem;border:none;border-radius:6px;font-size:.875rem;font-weight:600;cursor:pointer}
.dg-cc-btn--accept{background:#2563eb;color:#fff}.dg-cc-btn--accept:hover{background:#1d4ed8}
.dg-cc-btn--reject{background:#e2e8f0;color:#334155}.dg-cc-btn--reject:hover{background:#cbd5e1}
.dg-cc-btn--settings{background:transparent;color:#2563eb;text-decoration:underline;padding:.5rem .25rem}
.dg-cc-btn--save{background:#16a34a;color:#fff}.dg-cc-btn--save:hover{background:#15803d}
CSS;
    }

    /** Client-seitige Steuerung (acceptAll, rejectAll, saveCustom). */
    public static function bannerJs(): string
    {
        $cookieName = self::COOKIE_NAME;
        $lifetime = self::COOKIE_LIFETIME;

        return <<<JS
window.dgCookieConsent={
  setCookie:function(data){
    var val=btoa(JSON.stringify(data));
    var d=new Date();d.setTime(d.getTime()+{$lifetime}*1000);
    document.cookie="{$cookieName}="+val+";expires="+d.toUTCString()+";path=/;SameSite=Lax;Secure";
  },
  hide:function(){var el=document.getElementById('dg-cookie-consent');if(el)el.remove();},
  acceptAll:function(){
    this.setCookie({necessary:true,functional:true,analytics:true,marketing:true});
    this.hide();location.reload();
  },
  rejectAll:function(){
    this.setCookie({necessary:true,functional:false,analytics:false,marketing:false});
    this.hide();location.reload();
  },
  toggleDetails:function(){
    var d=document.getElementById('dg-cc-details');
    var s=document.getElementById('dg-cc-save');
    var show=d.style.display==='none';
    d.style.display=show?'block':'none';
    s.style.display=show?'inline-block':'none';
  },
  saveCustom:function(){
    var d=document.getElementById('dg-cc-details');
    var inputs=d.querySelectorAll('input[type=checkbox]');
    var data={necessary:true};
    inputs.forEach(function(cb){if(!cb.disabled)data[cb.name]=cb.checked;});
    this.setCookie(data);
    this.hide();location.reload();
  }
};
JS;
    }

    /** @return string HTML table for Datenschutzerklärung. */
    public static function cookieListHtml(): string
    {
        $html = '<h3>Verwendete Cookies</h3>';
        $html .= '<table><thead><tr><th>Name</th><th>Zweck</th><th>Kategorie</th><th>Speicherdauer</th></tr></thead><tbody>';

        $html .= '<tr><td>' . self::COOKIE_NAME . '</td><td>Speichert Ihre Cookie-Einstellungen</td><td>Notwendig</td><td>1 Jahr</td></tr>';

        $sessionName = (string) App::config('session_name', 'dg_crm_session');
        $html .= '<tr><td>' . htmlspecialchars($sessionName) . '</td><td>Session-Cookie für Anmeldung</td><td>Notwendig</td><td>Sitzungsende</td></tr>';

        if (class_exists('WebsiteAnalytics') && WebsiteAnalytics::isConfigured()) {
            $ids = WebsiteAnalytics::configuredIds();
            if ($ids['gtm_id'] !== '') {
                $html .= '<tr><td>_ga, _gid, _gat, …</td><td>Google Tag Manager / ggf. eingebundene Tags ('
                    . htmlspecialchars($ids['gtm_id']) . ')</td><td>Statistik</td><td>bis 2 Jahre</td></tr>';
            } elseif ($ids['ga_id'] !== '') {
                $html .= '<tr><td>_ga, _ga_*, _gid</td><td>Google Analytics 4 ('
                    . htmlspecialchars($ids['ga_id']) . ')</td><td>Statistik</td><td>bis 2 Jahre</td></tr>';
            }
        }

        $html .= '</tbody></table>';

        return $html;
    }
}
