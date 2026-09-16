#!/usr/bin/env bash
# Kontakte-Schulungsvideos: Screenshots + Render (Überblick + Felder 2a–2f + optional).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

BASE_URL="${BASE_URL:-https://ganz-soft.de}"
USER_ID="${USER_ID:-9}"
LOCALE="${LOCALE:-de}"
KDIR="$ROOT/storage/media/training/kontakte"

capture() {
  local html="$1" png="$2" regions="$3"
  shift 3
  python3 bin/academy-capture-page.py "$html" "$png" "$regions" "$@"
}

EDIT_FIELDS=(
  login='input[name="login"]'
  salutation='select[name="salutation"]'
  first_name='input[name="first_name"]'
  last_name='input[name="last_name"]'
  display_name='input[name="display_name"]'
  company_name='input[name="company_name"]'
  contact_role='select[name="contact_role"]'
  employer_company_id='select[name="employer_company_id"]'
  employer_responsibility='input[name="employer_responsibility"]'
  employer_work_email='input[name="employer_work_email"]'
  employer_work_phone='input[name="employer_work_phone"]'
  employer_availability='input[name="employer_availability"]'
  customer_number='input[name="customer_number"]'
  supplier_number='input[name="supplier_number"]'
  supplier_customer_number='input[name="supplier_customer_number"]'
  tax_number='input[name="tax_number"]'
  vat_id='input[name="vat_id"]'
  commercial_register='input[name="commercial_register"]'
  weee_registration='input[name="weee_registration"]'
  email='input[name="email"]'
  email_2='input[name="email_2"]'
  phone_1='input[name="phone_1"]'
  phone_2='input[name="phone_2"]'
  website='input[name="website"]'
  address1_street='input[name="address1_street"]'
  address1_extra='input[name="address1_extra"]'
  address1_city='input[name="address1_city"]'
  address1_postal='input[name="address1_postal"]'
  address1_country='input[name="address1_country"]'
  bank_type='select[name="bank_accounts[0][type]"]'
  bank_label='input[name="bank_accounts[0][label]"]'
  bank_account_holder='input[name="bank_accounts[0][account_holder]"]'
  bank_iban='input[name="bank_accounts[0][iban]"]'
  bank_bic='input[name="bank_accounts[0][bic]"]'
  bank_name='input[name="bank_accounts[0][bank_name]"]'
  social_linkedin='input[name="social_linkedin"]'
  social_xing='input[name="social_xing"]'
  social_facebook='input[name="social_facebook"]'
  social_instagram='input[name="social_instagram"]'
  social_x='input[name="social_x"]'
  social_youtube='input[name="social_youtube"]'
  social_tiktok='input[name="social_tiktok"]'
  social_codehost='input[name="social_github"]'
)

FIRMA_FIELDS=(
  ce_person='select[name="company_employees[0][person_contact_id]"]'
  ce_responsibility='input[name="company_employees[0][responsibility]"]'
  ce_work_email='input[name="company_employees[0][work_email]"]'
  ce_work_phone='input[name="company_employees[0][work_phone]"]'
  ce_availability='input[name="company_employees[0][availability]"]'
)

NEW_FIELDS=(
  auto_create_mailbox='#contact_auto_create_mailbox'
  email='input[name="email"]'
  email_2='input[name="email_2"]'
  phone_1='input[name="phone_1"]'
  phone_2='input[name="phone_2"]'
  website='input[name="website"]'
)

EMPLOYEE_FIELDS=(
  employee_birth_date='input[name="employee[birth_date]"]'
  employee_gender='select[name="employee[gender]"]'
  employee_marital_status='input[name="employee[marital_status]"]'
  employee_spouse_name='input[name="employee[spouse_name]"]'
  employee_social_security_status='select[name="employee[social_security_status]"]'
  employee_social_filing_office='select[name="employee[social_filing_office]"]'
  employee_social_security_number='input[name="employee[social_security_number]"]'
  employee_health_insurance='input[name="employee[health_insurance]"]'
  employee_health_insurance_number='input[name="employee[health_insurance_number]"]'
  employee_health_status='textarea[name="employee[health_status]"]'
  employee_treatment_needs='textarea[name="employee[treatment_needs]"]'
  employee_disability_degree='input[name="employee[disability_degree]"]'
  employee_disability_supplementary_codes='input[name="employee[disability_supplementary_codes]"]'
  employee_disabilities='textarea[name="employee[disabilities]"]'
  employee_employment_relationship='input[name="employee[employment_relationship]"]'
  employee_employer_site='input[name="employee[employer_site]"]'
  employee_work_location='input[name="employee[work_location]"]'
  employee_job_type='input[name="employee[job_type]"]'
  employee_entry_date='input[name="employee[entry_date]"]'
  employee_exit_date='input[name="employee[exit_date]"]'
  employee_contract_start='input[name="employee[contract_start]"]'
  employee_contract_end='input[name="employee[contract_end]"]'
  employee_working_hours='input[name="employee[working_hours]"]'
  employee_daily_work_minutes='input[name="employee[daily_work_minutes]"]'
  employee_employment_type='select[name="employee[employment_type]"]'
  employee_overtime_allowed='input[name="employee[overtime_allowed]"]'
  employee_salary='input[name="employee[salary]"]'
  employee_subsidy_amount='input[name="employee[subsidy_amount]"]'
  employee_subsidy_carrier='input[name="employee[subsidy_carrier]"]'
  employee_qualifications='textarea[name="employee[qualifications]"]'
  employee_performance_notes='textarea[name="employee[performance_notes]"]'
  employee_behavior_notes='textarea[name="employee[behavior_notes]"]'
  employee_driver_license_classes='input[name="employee[driver_license_classes]"]'
  employee_driver_license_valid_until='input[name="employee[driver_license_valid_until]"]'
)

echo "==> 1/4 Kontakte-HTML exportieren"
scp bin/academy-export-kontakte-html.php allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/bin/
ssh allinkl-ganzom "cd /www/htdocs/w0217246/ganz-soft.de && php bin/academy-export-kontakte-html.php --base=${BASE_URL}/ --user-id=${USER_ID}"
mkdir -p "$KDIR"
scp allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/storage/media/training/kontakte/*.html \
    allinkl-ganzom:/www/htdocs/w0217246/ganz-soft.de/storage/media/training/kontakte/kontakte-export.meta.json \
    "$KDIR/" 2>/dev/null || true

echo "==> 2/4 Screenshots + Regionen"
python3 bin/academy-capture-page.py "$KDIR/kontakte-list.html" "$KDIR/kontakte-list.png"
capture "$KDIR/kontakte-edit.html" "$KDIR/kontakte-edit.png" "$KDIR/kontakte-edit-regions.json" "${EDIT_FIELDS[@]}"
capture "$KDIR/kontakte-edit-firma.html" "$KDIR/kontakte-edit-firma.png" "$KDIR/kontakte-edit-firma-regions.json" "${FIRMA_FIELDS[@]}"
capture "$KDIR/kontakte-new.html" "$KDIR/kontakte-new.png" "$KDIR/kontakte-new-regions.json" "${NEW_FIELDS[@]}"
capture "$KDIR/kontakte-edit-mitarbeiter.html" "$KDIR/kontakte-edit-mitarbeiter.png" "$KDIR/kontakte-edit-mitarbeiter-regions.json" "${EMPLOYEE_FIELDS[@]}"

if [[ ! -f storage/media/training/allgemein/dashboard-capture.png ]]; then
  echo "Dashboard-Screenshot fehlt — bitte zuerst bin/academy-build-dashboard-video.sh (Capture) ausführen"
  exit 1
fi

echo "==> 3/4 Videos rendern"
SCRIPTS=(
  kontakte-ueberblick
  kontakte-felder-stamm
  kontakte-felder-kunde-lieferant
  kontakte-felder-kommunikation
  kontakte-felder-adresse
  kontakte-felder-bank
  kontakte-felder-social
  kontakte-felder-mitarbeiter
)
for script in "${SCRIPTS[@]}"; do
  echo "--- $script"
  python3 bin/academy-generate-scene-video.py --locale "$LOCALE" --script "$script"
done

echo "==> 4/4 Altes Sammel-Video entfernen"
rm -f "$KDIR/kontakte-felder.mp4" "$KDIR/kontakte-felder.vtt" "$KDIR/kontakte-felder.meta.json"

echo "Fertig: $KDIR/kontakte-felder-*.mp4 und kontakte-ueberblick.mp4"
