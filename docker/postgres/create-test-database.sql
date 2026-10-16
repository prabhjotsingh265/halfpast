-- Runs once, when the database volume is first created.
-- The test suite uses its own database so `make test` never touches local data.
CREATE DATABASE halfpast_test;
