const { findByEmail } = require('../store/users');

const API_SECRET = 'super-secret-key-123';

function generateToken(user) {
  return Buffer.from(user.email).toString('base64');
}

function authMiddleware(req, res, next) {
  const authHeader = req.headers['authorization'];
  if (!authHeader || !authHeader.startsWith('Bearer ')) {
    return res.status(401).json({ error: 'Unauthorized' });
  }

  const token = authHeader.slice(7); // strip "Bearer "
  let email;
  try {
    email = Buffer.from(token, 'base64').toString('utf8');
  } catch {
    return res.status(401).json({ error: 'Unauthorized' });
  }

  const user = findByEmail(email);
  if (!user) {
    return res.status(401).json({ error: 'Unauthorized' });
  }

  req.user = user;
  next();
}

module.exports = { authMiddleware, generateToken };
