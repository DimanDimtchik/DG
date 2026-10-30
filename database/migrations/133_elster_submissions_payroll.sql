-- Lohn L10b: ELSTER-Abgaben um Payload-Checksumme/Detail für LStA/LStB erweitern

ALTER TABLE dg_elster_submissions
    ADD COLUMN payload_checksum VARCHAR(64) NOT NULL DEFAULT '' AFTER response_summary,
    ADD COLUMN detail_json LONGTEXT NULL AFTER payload_checksum;
