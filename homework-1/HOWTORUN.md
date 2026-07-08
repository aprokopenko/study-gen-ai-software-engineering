# ▶️ How to Run

## Requirements

- [Docker](https://docs.docker.com/get-docker/) (with Compose v2)
- [Make](https://www.gnu.org/software/make/) — pre-installed on most Linux distros; on macOS install via Homebrew (`brew install make`)

## Setup & Run

1. **Clone the repo and enter the directory**
   ```bash
   git clone <repo-url>
   cd homework-1
   ```

2. **Build, start containers, install dependencies, and initialise the database**
   ```bash
   make setup
   ```

3. **The API is now available at** `http://localhost:3000`

### curl tests

**List all transactions**
```bash
curl -s http://localhost:3000/transactions
```

**Get account summary**
```bash
curl -s http://localhost:3000/accounts/ACC-00001/summary
```

## Common Commands

| Command | Description |
|---|---|
| `make up` | Start containers (no rebuild) |
| `make down` | Stop containers |
| `make restart` | Restart containers |
| `make logs` | Tail container logs |
| `make shell` | Open a shell inside the PHP container |
| `make phpunit` | Run the test suite |
