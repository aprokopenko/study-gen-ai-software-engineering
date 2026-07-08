CREATE TABLE IF NOT EXISTS transactions (
    id           VARCHAR(36) PRIMARY KEY,
    from_account VARCHAR(20),
    to_account   VARCHAR(20),
    amount       DECIMAL(15,2) NOT NULL,
    currency     CHAR(3)       NOT NULL,
    type         VARCHAR(20)   NOT NULL,
    timestamp    VARCHAR(30)   NOT NULL,
    status       VARCHAR(20)   NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_transactions_from   ON transactions(from_account);
CREATE INDEX IF NOT EXISTS idx_transactions_to     ON transactions(to_account);
CREATE INDEX IF NOT EXISTS idx_transactions_type   ON transactions(type);
CREATE INDEX IF NOT EXISTS idx_transactions_status ON transactions(status);
