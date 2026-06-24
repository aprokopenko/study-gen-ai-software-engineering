# 📚 Корисні ресурси з курсу

## 🧠 Skills (навички для AI-агентів)

### Офіційні та довідкові

| Ресурс | Опис |
|--------|------|
| [anthropics/skills](https://github.com/anthropics/skills) | Офіційний репозиторій Anthropic зі скілами для Claude Code — від креативних (арт, музика, дизайн) до технічних (тестування, генерація MCP-серверів) та enterprise-задач (комунікації, брендинг). |
| [The Complete Guide to Building Skills for Claude (PDF)](https://resources.anthropic.com/hubfs/The-Complete-Guide-to-Building-Skill-for-Claude.pdf) | Повний гайд від Anthropic зі створення скілів — основи, планування, тестування, дистрибуція, патерни та YAML frontmatter reference. |
| [mgechev/skills-best-practices](https://github.com/mgechev/skills-best-practices) | Рекомендації від Minko Gechev (Angular team lead у Google) щодо написання професійних скілів: структура, оптимізація frontmatter, progressive disclosure, lean context window. Включає інструмент [skillgrade](https://github.com/mgechev/skillgrade) для валідації. |

### Каталоги та маркетплейси

| Ресурс | Опис |
|--------|------|
| [skills.sh](https://skills.sh/) | Відкрита екосистема скілів для AI-агентів від Vercel. Підтримує 20+ агентів (Claude Code, Cursor, Codex, Copilot, Windsurf тощо). Встановлення одною командою: `npx skills add <owner/repo>`. Має лідерборд популярності. |
| [aitmpl.com/skills](https://www.aitmpl.com/skills) | Каталог 1000+ компонентів для Claude Code — агенти, команди, скіли, хуки, MCP-інтеграції та шаблони. Можна збирати свій стек і встановлювати пачкою. |
| [travisvn/awesome-claude-skills](https://github.com/travisvn/awesome-claude-skills) | Курований awesome-list скілів для Claude — розділи за категоріями: документи, розробка, дані, письмо, медіа, безпека, автоматизація тощо. |

---

## 📖 Документація Claude Code

| Ресурс | Опис |
|--------|------|
| [code.claude.com/docs/en/goal](https://code.claude.com/docs/en/goal) | Документація команди `/goal` у Claude Code — дозволяє задати умову завершення, і Claude працює автономно крок за кроком, поки ціль не буде досягнута. |

---

## 🔌 Model Context Protocol (MCP)

| Ресурс | Опис |
|--------|------|
| [modelcontextprotocol.io](https://modelcontextprotocol.io/docs/getting-started/intro) | Офіційна документація MCP — відкритого стандарту від Anthropic для підключення AI-застосунків до зовнішніх систем (API, бази даних, сервіси). Часто називають «USB-C для AI» — єдиний протокол замість десятків пропрієтарних інтеграцій. |

---

## 💡 Оптимізація використання Claude

| Ресурс | Опис |
|--------|------|
| [How to stop hitting Claude usage limits](https://ruben.substack.com/p/how-to-stop-hitting-claude-usage) | Стаття з 23 звичками для економії Claude-токенів, ранжованими від маловідомих до очевидних. Ключовий інсайт: Claude перечитує всю бесіду з початку при кожному повідомленні, тому довгі розмови = великі витрати токенів. |
| [dtnewman/burn-baby-burn](https://github.com/dtnewman/burn-baby-burn) | 🔥 Сатиричний проєкт — bash-скрипт, який навмисно спалює токени Claude Code/Codex. Жарт про корпоративну культуру: «нічого не допомагає кар'єрі краще за шестизначний рахунок за токени». Корисний для розуміння того, як рахуються токени (і чому їх варто економити). |

---

## 🛠 Інструменти для розробника

| Ресурс | Опис |
|--------|------|
| [Warp](https://www.warp.dev/) | Сучасний термінал з вбудованим AI-агентом. Agent Mode дозволяє виконувати складні багатокрокові задачі з натуральної мови прямо в терміналі. Має plan-mode для spec-driven розробки. Доступний на macOS, Linux, Windows. |
| [Superwhisper](https://superwhisper.com/) | AI-диктування для Mac, Windows та iOS. Перетворює мовлення на форматований текст у будь-якому додатку (email, Slack, код). Працює офлайн, підтримує 100+ мов, автоматично адаптує стиль під контекст (технічний у редакторі коду, розмовний у месенджері). |
| [Speech Note](https://github.com/mkiol/dsnote) | Open-source додаток для Linux (є на [Flathub](https://flathub.org/en/apps/net.mkiol.SpeechNote)). Поєднує Speech-to-Text, Text-to-Speech та машинний переклад — все повністю офлайн, без мережі. Підтримує сотні моделей для різних мов, які завантажуються локально. Альтернатива Superwhisper для Linux-користувачів. |
