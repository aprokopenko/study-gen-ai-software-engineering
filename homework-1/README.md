# 🏦 Homework 1: Banking Transactions API

> **Student Name**: [Your Name]  
> **Date Submitted**: [Date]  
> **AI Tools Used**: Claude Code (plugin for PHPStorm/VS Code), Kiro CLI

---

## 📋 Project Overview

A REST API for bank account transactions built with **PHP 8.5** and **Slim 4** framework, using **Medoo** as a lightweight ORM over **SQLite**, with **PHP-DI** for dependency injection. The app runs in Docker (Nginx + PHP-FPM) and is exposed on port `3000`.

The API handles core banking operations — creating and listing transactions, filtering transaction history, and querying account balances. Transactions follow a structured model with fields like `fromAccount`, `toAccount`, `amount`, `currency`, `type`, and `status`.

**Tech stack at a glance:**
- Runtime: PHP 8.5 (FPM) + Nginx via Docker
- Framework: Slim 4 (PSR-7)
- Database: SQLite via Medoo 2.x
- DI Container: PHP-DI 7


<div align="center">

*This project was completed as part of the AI-Assisted Development course.*

</div>
