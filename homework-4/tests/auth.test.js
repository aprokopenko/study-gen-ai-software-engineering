const request = require('supertest');
const app = require('../src/app');
const { clearUsers } = require('../src/store/users');

beforeEach(() => clearUsers());

describe('Auth API', () => {
  test('POST /api/register creates a new user', async () => {
    const res = await request(app)
      .post('/api/register')
      .send({ username: 'john', email: 'john@test.com', password: 'pass123' });
    expect(res.status).toBe(201);
    expect(res.body.user.email).toBe('john@test.com');
    expect(res.body.token).toBeDefined();
  });

  test('GET /api/profile without token returns 401', async () => {
    const res = await request(app).get('/api/profile');
    expect(res.status).toBe(401);
  });
});
