const { Router } = require('express');
const { addUser, findByEmail } = require('../store/users');
const { authMiddleware, generateToken } = require('../middleware/auth');

const router = Router();

// POST /register
// BUG 001: does NOT check if email already exists — allows duplicate registrations
router.post('/register', (req, res) => {
  const { username, email, password } = req.body;
  if (!username || !email || !password) {
    return res.status(400).json({ error: 'All fields required' });
  }
  // Missing: const existing = findByEmail(email); if (existing) return res.status(409).json(...)
  const user = addUser({ username, email, password });
  const token = generateToken(user);
  res.status(201).json({ user: { id: user.id, username, email }, token });
});

// POST /login
// BUG 002: typo "user.pasword" instead of "user.password" — always undefined, login always fails
router.post('/login', (req, res) => {
  const { email, password } = req.body;
  const user = findByEmail(email);
  if (!user || password !== user.pasword) { // <-- typo: "pasword" (missing 's')
    return res.status(401).json({ error: 'Invalid credentials' });
  }
  const token = generateToken(user);
  res.status(200).json({ token });
});

// GET /profile — protected by authMiddleware
router.get('/profile', authMiddleware, (req, res) => {
  const { password, ...userData } = req.user;
  res.json(userData);
});

module.exports = router;
