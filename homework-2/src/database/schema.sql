CREATE TABLE IF NOT EXISTS tickets (
    id                        TEXT PRIMARY KEY,
    customer_id               TEXT NOT NULL,
    customer_email            TEXT NOT NULL,
    customer_name             TEXT NOT NULL,
    subject                   TEXT NOT NULL,
    description               TEXT NOT NULL,
    category                  TEXT NOT NULL,
    priority                  TEXT NOT NULL,
    status                    TEXT NOT NULL DEFAULT 'new',
    assigned_to               TEXT,
    tags                      TEXT NOT NULL DEFAULT '[]',
    metadata_source           TEXT,
    metadata_browser          TEXT,
    metadata_device_type      TEXT,
    classification_confidence REAL,
    classification_reasoning  TEXT,
    classification_keywords   TEXT,
    created_at                TEXT NOT NULL,
    updated_at                TEXT NOT NULL,
    resolved_at               TEXT
);
CREATE INDEX IF NOT EXISTS idx_tickets_status     ON tickets(status);
CREATE INDEX IF NOT EXISTS idx_tickets_category   ON tickets(category);
CREATE INDEX IF NOT EXISTS idx_tickets_priority   ON tickets(priority);
CREATE INDEX IF NOT EXISTS idx_tickets_customer   ON tickets(customer_id);
CREATE INDEX IF NOT EXISTS idx_tickets_created_at ON tickets(created_at);

CREATE TABLE IF NOT EXISTS ticket_logs (
    id         TEXT PRIMARY KEY,
    ticket_id  TEXT NOT NULL,
    event      TEXT NOT NULL,
    payload    TEXT,
    created_at TEXT NOT NULL,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
);
