const crypto = require('crypto');
const { findByEmail } = require('../store/users');

const API_SECRET = process.env.API_SECRET || 'super-secret-key-123';

function generateToken(user) {
  const payload = Buffer.from(user.email).toString('base64');
  const signature = crypto
    .createHmac('sha256', API_SECRET)
    .update(payload)
    .digest('hex');
  return `${payload}.${signature}`;
}

function authMiddleware(req, res, next) {
  const authHeader = req.headers['authorization'];
  if (!authHeader || !authHeader.startsWith('Bearer ')) {
    return res.status(401).json({ error: 'Unauthorized' });
  }

  const token = authHeader.slice(7); // strip "Bearer "

  // Parse token into payload and signature
  const parts = token.split('.');
  if (parts.length !== 2) {
    return res.status(401).json({ error: 'Unauthorized' });
  }

  const [payload, signature] = parts;

  // Verify signature
  const expectedSignature = crypto
    .createHmac('sha256', API_SECRET)
    .update(payload)
    .digest('hex');

  try {
    crypto.timingSafeEqual(Buffer.from(signature), Buffer.from(expectedSignature));
  } catch {
    return res.status(401).json({ error: 'Unauthorized' });
  }

  let email;
  try {
    email = Buffer.from(payload, 'base64').toString('utf8');
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
