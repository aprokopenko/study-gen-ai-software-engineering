const { Router } = require('express');
const { addUser, findByEmail } = require('../store/users');
const { authMiddleware, generateToken } = require('../middleware/auth');

const router = Router();

// POST /register
router.post('/register', (req, res) => {
  const { username, email, password } = req.body;
  if (!username || !email || !password) {
    return res.status(400).json({ error: 'All fields required' });
  }
  const user = addUser({ username, email, password });
  const token = generateToken(user);
  res.status(201).json({ user: { id: user.id, username, email }, token });
});

// POST /login
router.post('/login', (req, res) => {
  const { email, password } = req.body;
  const user = findByEmail(email);
  if (!user || password !== user.pasword) {
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
