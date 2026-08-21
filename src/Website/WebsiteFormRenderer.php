<?php
declare(strict_types=1);

/**
 * Renders a website form as public HTML (and shortcode helper).
 */
final class WebsiteFormRenderer
{
    /**
     * HTML for embedding a published form on a public page.
     *
     * @param array<string, mixed> $form Mapped form from WebsiteFormRepository
     * @param array{ok?: bool, error?: string}|null $flash
     */
    public static function render(array $form, ?array $flash = null): string
    {
        $id = (int) ($form['id'] ?? 0);
        $definition = is_array($form['definition'] ?? null) ? $form['definition'] : WebsiteFormRepository::emptyDefinition();
        $fields = is_array($definition['fields'] ?? null) ? $definition['fields'] : [];
        $settings = is_array($definition['settings'] ?? null) ? $definition['settings'] : [];
        $honeypot = !empty($settings['honeypot']);
        $captcha = !empty($settings['captcha']);

        $html = '<div class="ws-form" data-form-id="' . $id . '">';
        if (!empty($flash['ok'])) {
            $msg = (string) ($settings['success_message'] ?? 'Vielen Dank!');
            $html .= '<div class="ws-form__flash ws-form__flash--ok">' . View::escape($msg) . '</div>';
        }
        if (!empty($flash['error'])) {
            $html .= '<div class="ws-form__flash ws-form__flash--error">' . View::escape((string) $flash['error']) . '</div>';
        }

        $html .= '<form method="post" action="/formular-senden" enctype="multipart/form-data" class="ws-form__form">';
        $html .= '<input type="hidden" name="_csrf" value="' . View::escape(Csrf::token()) . '">';
        $html .= '<input type="hidden" name="form_id" value="' . $id . '">';
        if ($honeypot) {
            $html .= '<div class="ws-form__hp" aria-hidden="true" style="position:absolute;left:-9999px;height:0;overflow:hidden;">'
                . '<label>Website <input type="text" name="website_url" tabindex="-1" autocomplete="off"></label></div>';
        }

        $html .= '<div class="ws-form__grid">';
        $submitHtml = '';
        $needsAppointmentJs = false;
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            $type = (string) ($field['type'] ?? '');
            if ($type === 'article') {
                $field = self::withArticleOptions($field);
            }
            if ($type === 'appointment') {
                $needsAppointmentJs = true;
            }
            if ($type === 'intent' && empty($field['options'])) {
                $field['options'] = [
                    ['value' => 'termin', 'label' => 'Wegen eines Termins'],
                    ['value' => 'artikel', 'label' => 'Wegen Artikel / Dienstleistung'],
                    ['value' => 'allgemein', 'label' => 'Allgemeine Anfrage'],
                ];
            }
            if ($type === 'submit') {
                $submitHtml = self::renderField($field);
                continue;
            }
            $html .= self::renderField($field);
        }
        if ($captcha) {
            $html .= WebsiteFormCaptcha::renderFieldHtml();
        }
        $html .= $submitHtml !== '' ? $submitHtml : self::renderField([
            'type' => 'submit',
            'label' => 'Absenden',
            'name' => 'submit',
            'width' => 12,
        ]);
        $html .= '</div></form>';
        if ($needsAppointmentJs) {
            $html .= self::appointmentLookupScript();
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * @param array<string, mixed> $field
     * @return array<string, mixed>
     */
    private static function withArticleOptions(array $field): array
    {
        if (!empty($field['options']) && is_array($field['options'])) {
            return $field;
        }
        $opts = [];
        if (Database::isConfigured()) {
            foreach (CalendarArticleRepository::all(true) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $opts[] = [
                    'value' => (string) ((int) ($row['id'] ?? 0)),
                    'label' => (string) ($row['title'] ?? ('#' . ($row['id'] ?? ''))),
                ];
            }
        }
        $field['options'] = $opts;

        return $field;
    }

    private static function appointmentLookupScript(): string
    {
        return <<<'HTML'
<script>
(function(){
  function statusEl(inp){
    var id=inp.getAttribute('data-ws-appt-status');
    var el=id?document.getElementById(id):null;
    if(!el){
      el=document.createElement('small');
      el.className='ws-form__help ws-form__appt-status';
      el.id='ws-appt-st-'+Math.random().toString(36).slice(2,8);
      inp.setAttribute('data-ws-appt-status', el.id);
      inp.parentNode.appendChild(el);
    }
    return el;
  }
  function load(inp){
    var code=(inp.value||'').trim();
    var st=statusEl(inp);
    if(!code){ st.textContent=''; st.className='ws-form__help ws-form__appt-status'; return; }
    st.textContent='Prüfe Buchungsnummer…';
    st.className='ws-form__help ws-form__appt-status';
    fetch('/api/website-form/appointments?code='+encodeURIComponent(code),{credentials:'same-origin'})
      .then(function(r){return r.json();})
      .then(function(d){
        var item=(d&&d.items&&d.items[0])||null;
        if(item){
          inp.value=item.code||code;
          st.textContent='Gefunden: '+item.label;
          st.className='ws-form__help ws-form__appt-status ws-form__appt-status--ok';
        }else{
          st.textContent='Keine passende Buchung gefunden. Bitte Nummer aus der Bestätigungsmail prüfen.';
          st.className='ws-form__help ws-form__appt-status ws-form__appt-status--err';
        }
      })
      .catch(function(){ st.textContent=''; });
  }
  document.querySelectorAll('input[data-ws-appointment]').forEach(function(inp){
    inp.addEventListener('blur',function(){load(inp);});
    inp.addEventListener('change',function(){load(inp);});
  });
})();
</script>
HTML;
    }

    /**
     * Replace [dg-form id="123"] shortcodes in HTML.
     */
    public static function expandShortcodes(string $html, ?array $flashByFormId = null): string
    {
        return (string) preg_replace_callback(
            '/\[dg-form\s+id=["\']?(\d+)["\']?\s*\]/i',
            static function (array $m) use ($flashByFormId): string {
                $formId = (int) $m[1];
                $form = WebsiteFormRepository::findPublished($formId);
                if ($form === null) {
                    return '<!-- Formular #' . $formId . ' nicht verfügbar -->';
                }
                $flash = is_array($flashByFormId[$formId] ?? null) ? $flashByFormId[$formId] : null;

                return self::render($form, $flash);
            },
            $html
        );
    }

    /**
     * @param array<string, mixed> $field
     */
    private static function renderField(array $field): string
    {
        $type = (string) ($field['type'] ?? 'text');
        $name = View::escape((string) ($field['name'] ?? 'field'));
        $label = View::escape((string) ($field['label'] ?? ''));
        $placeholder = View::escape((string) ($field['placeholder'] ?? ''));
        $help = View::escape((string) ($field['help'] ?? ''));
        $required = !empty($field['required']);
        $width = max(3, min(12, (int) ($field['width'] ?? 12)));
        $reqAttr = $required ? ' required' : '';
        $reqMark = $required ? ' <span class="ws-form__req">*</span>' : '';

        if ($type === 'heading') {
            return '<div class="ws-form__cell ws-form__cell--' . $width . '"><h3 class="ws-form__heading">' . $label . '</h3></div>';
        }
        if ($type === 'paragraph') {
            return '<div class="ws-form__cell ws-form__cell--' . $width . '"><p class="ws-form__paragraph">' . $label . '</p></div>';
        }
        if ($type === 'submit') {
            $btn = $label !== '' ? $label : 'Absenden';
            return '<div class="ws-form__cell ws-form__cell--' . $width . '">'
                . '<button type="submit" class="ws-btn">' . $btn . '</button></div>';
        }

        $inner = '';
        if ($type === 'textarea') {
            $rows = max(2, min(20, (int) ($field['rows'] ?? 4)));
            $inner = '<textarea name="' . $name . '" rows="' . $rows . '" placeholder="' . $placeholder . '"' . $reqAttr . '></textarea>';
        } elseif ($type === 'appointment') {
            $ph = $placeholder !== '' ? $placeholder : 'z. B. DG-7K2M9P4Q';
            $inner = '<input type="text" name="' . $name . '" value="" placeholder="' . View::escape($ph) . '"'
                . ' autocomplete="off" spellcheck="false" data-ws-appointment="1"' . $reqAttr . '>';
        } elseif ($type === 'select' || $type === 'article') {
            $inner = '<select name="' . $name . '"' . $reqAttr
                . '><option value="">— Bitte wählen —</option>';
            foreach ((array) ($field['options'] ?? []) as $opt) {
                if (!is_array($opt)) {
                    continue;
                }
                $inner .= '<option value="' . View::escape((string) ($opt['value'] ?? '')) . '">'
                    . View::escape((string) ($opt['label'] ?? '')) . '</option>';
            }
            $inner .= '</select>';
        } elseif ($type === 'intent') {
            $inner = '<div class="ws-form__choices">';
            $opts = (array) ($field['options'] ?? [
                ['value' => 'termin', 'label' => 'Wegen eines Termins'],
                ['value' => 'artikel', 'label' => 'Wegen Artikel / Dienstleistung'],
                ['value' => 'allgemein', 'label' => 'Allgemeine Anfrage'],
            ]);
            foreach ($opts as $opt) {
                if (!is_array($opt)) {
                    continue;
                }
                $val = View::escape((string) ($opt['value'] ?? ''));
                $lbl = View::escape((string) ($opt['label'] ?? ''));
                $inner .= '<label class="ws-form__choice"><input type="radio" name="' . $name . '" value="' . $val . '"' . $reqAttr . '> ' . $lbl . '</label>';
            }
            $inner .= '</div>';
        } elseif ($type === 'checkbox') {
            $inner = '<div class="ws-form__choices">';
            foreach ((array) ($field['options'] ?? []) as $i => $opt) {
                if (!is_array($opt)) {
                    continue;
                }
                $val = View::escape((string) ($opt['value'] ?? ''));
                $lbl = View::escape((string) ($opt['label'] ?? ''));
                $inner .= '<label class="ws-form__choice"><input type="checkbox" name="' . $name . '[]" value="' . $val . '"> ' . $lbl . '</label>';
            }
            $inner .= '</div>';
        } elseif ($type === 'radio') {
            $inner = '<div class="ws-form__choices">';
            foreach ((array) ($field['options'] ?? []) as $opt) {
                if (!is_array($opt)) {
                    continue;
                }
                $val = View::escape((string) ($opt['value'] ?? ''));
                $lbl = View::escape((string) ($opt['label'] ?? ''));
                $inner .= '<label class="ws-form__choice"><input type="radio" name="' . $name . '" value="' . $val . '"' . $reqAttr . '> ' . $lbl . '</label>';
            }
            $inner .= '</div>';
        } elseif ($type === 'consent') {
            $inner = '<label class="ws-form__choice ws-form__consent"><input type="checkbox" name="'
                . $name . '" value="1"' . $reqAttr . '> <span>' . $label . $reqMark . '</span></label>';
            $label = '';
            $reqMark = '';
        } elseif ($type === 'file') {
            $accept = View::escape((string) ($field['accept'] ?? ''));
            $inner = '<input type="file" name="' . $name . '" accept="' . $accept . '"' . $reqAttr . '>';
        } else {
            $inputType = match ($type) {
                'email' => 'email',
                'tel' => 'tel',
                default => 'text',
            };
            $inner = '<input type="' . $inputType . '" name="' . $name . '" placeholder="' . $placeholder . '"' . $reqAttr . '>';
        }

        $html = '<div class="ws-form__cell ws-form__cell--' . $width . '">';
        if ($label !== '' && $type !== 'consent') {
            $html .= '<label class="ws-form__label"><span>' . $label . $reqMark . '</span>' . $inner . '</label>';
        } else {
            $html .= $inner;
        }
        if ($help !== '') {
            $html .= '<small class="ws-form__help">' . $help . '</small>';
        }
        $html .= '</div>';

        return $html;
    }
}
