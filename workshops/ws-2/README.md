# Banking Transaction Parser & Fraud Detector

Парсер банківських транзакцій з автоматичним виявленням шахрайських патернів. Підтримує формати CSV, JSON та XML.

## Вимоги

- Python 3.10+
- Стандартна бібліотека Python (без зовнішніх залежностей)

## Запуск

### Демо (вбудовані тестові дані)

```bash
python3 main.py
# або явно
python3 main.py demo
```

### Один файл

```bash
python3 main.py data/sample.csv
python3 main.py data/sample.json
python3 main.py data/sample.xml
```

### Кілька файлів одночасно

```bash
python3 main.py data/sample.csv data/sample.json data/sample.xml
```

### Детальний вивід (усі спрацьовані транзакції)

```bash
python3 main.py data/sample.csv -v
python3 main.py data/sample.json --verbose
```

### Примусовий формат (якщо авто-детект не спрацював)

```bash
python3 main.py data/myfile.txt --format csv
python3 main.py data/myfile.txt --format json
python3 main.py data/myfile.txt --format xml
```

## Структура проекту

```
workshop2/
├── main.py                        # CLI
├── transaction_parser/
│   ├── models.py                  # датакласи Transaction, FraudAlert, ParseResult
│   ├── parsers.py                 # CSV / JSON / XML парсери + auto_parse()
│   └── fraud_detector.py          # правила виявлення шахрайства
└── data/
    ├── sample.csv
    ├── sample.json
    └── sample.xml
```

## Формати вхідних файлів

### CSV

Обов'язкові колонки: `id`, `timestamp`, `amount`, `currency`, `account_from`, `account_to`, `merchant`, `category`, `transaction_type`

```csv
id,timestamp,amount,currency,account_from,account_to,merchant,category,transaction_type,location
TXN001,2026-04-30 09:15:00,250.00,USD,ACC123,ACC456,Starbucks,Food,DEBIT,New York
```

Підтримувані формати дати: `YYYY-MM-DDTHH:MM:SS`, `YYYY-MM-DD HH:MM:SS`, `DD/MM/YYYY HH:MM:SS`

### JSON

Масив транзакцій або об'єкт із ключем `transactions`:

```json
[
  {
    "id": "J001",
    "timestamp": "2026-04-30T10:00:00",
    "amount": 500.00,
    "currency": "USD",
    "account_from": "ACC123",
    "account_to": "ACC456",
    "merchant": "Amazon",
    "category": "Shopping",
    "transaction_type": "DEBIT"
  }
]
```

### XML

Корінь може бути будь-яким, транзакції — теги `<transaction>` або `<txn>`:

```xml
<transactions>
  <transaction>
    <id>X001</id>
    <timestamp>2026-04-30T10:00:00</timestamp>
    <amount>500.00</amount>
    <currency>USD</currency>
    <account_from>ACC123</account_from>
    <account_to>ACC456</account_to>
    <merchant>Amazon</merchant>
    <category>Shopping</category>
    <transaction_type>DEBIT</transaction_type>
  </transaction>
</transactions>
```

## Правила виявлення шахрайства

| Правило | Серйозність | Умова |
|---|---|---|
| `LARGE_TRANSACTION` | HIGH / CRITICAL | Сума ≥ $10 000 |
| `STRUCTURING` | CRITICAL | 3+ транзакції в діапазоні $8 500–$9 999 протягом 60 хв з одного рахунку |
| `DUPLICATE_TRANSACTION` | HIGH | Однакові сума + мерчант + рахунок протягом 5 хв |
| `HIGH_VELOCITY` | HIGH | 5+ транзакцій з одного рахунку за 10 хв |
| `UNUSUAL_HOURS` | LOW | Час транзакції 01:00–04:59 |
| `ROUND_AMOUNT` | LOW | Сума кратна 1000 і ≥ 1000 |
| `ACCOUNT_MISMATCH` | MEDIUM | Відправник і отримувач — один і той самий рахунок |

Кожен алерт має числовий `score` від 1 до 100 — чим вище, тим підозріліше.

## Приклад виводу

```
────────────────────────────────────────────────────────────
  Fraud Analysis Summary
────────────────────────────────────────────────────────────
  Flagged  : 14 / 15 (93.3%)
  Alerts   : 17

  By Severity:
    CRITICAL    3
    HIGH       10
    MEDIUM      1
    LOW         3

  By Rule:
    · HIGH_VELOCITY                  6
    · STRUCTURING                    3
    · LARGE_TRANSACTION              2
    · DUPLICATE_TRANSACTION          2
    · ROUND_AMOUNT                   2
    · UNUSUAL_HOURS                  1
    · ACCOUNT_MISMATCH               1
```
