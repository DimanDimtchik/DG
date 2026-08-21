-- Öffentliche Buchungsnummer (nicht erratbar wie die interne ID)
SET NAMES utf8mb4;

ALTER TABLE dg_bookings
  ADD COLUMN booking_code VARCHAR(16) NULL DEFAULT NULL AFTER id,
  ADD UNIQUE KEY uq_booking_code (booking_code);
