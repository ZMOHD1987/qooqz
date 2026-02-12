-- Add entity_id to flash_sales for multi-tenant support
-- Each entity can manage its own flash sales
-- NULL entity_id means global flash sale (available to all)

ALTER TABLE flash_sales
ADD COLUMN entity_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER id,
ADD INDEX idx_flash_sales_entity (entity_id),
ADD CONSTRAINT fk_flash_sales_entity
    FOREIGN KEY (entity_id) REFERENCES entities(id)
    ON DELETE SET NULL;
