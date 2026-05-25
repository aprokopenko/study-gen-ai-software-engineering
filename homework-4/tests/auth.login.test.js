/**
 * Tests for the login password-comparison fix introduced in Bug 002.
 *
 * Root cause: src/routes/auth.js line 25 referenced `user.pasword` (typo)
 * instead of `user.password`, causing every login to return 401 regardless
 * of whether the credentials were correct.
 *
 * Only behaviour touched by that one-character fix is covered here.
 * The in-memory store is cleared before every test via clearUsers() so each
 * test starts from an empty state (Independent, Repeatable).
 */
const request = require('supertest');
const app = require('../src/app');
const { clearUsers } = require('../src/store/users');

beforeEach(() => clearUsers());

describe('POST /api/login — password comparison fix (Bug 002 fix)', () => {
  const registeredUser = {
    username: 'alice',
    email: 'alice@example.com',
    password: 'correct-password',
  };

  /** Helper: register the user, ignore the result. */
  const register = () =>
    request(app).post('/api/register').send(registeredUser);

  // -------------------------------------------------------------------------
  // Happy-path: the core behaviour that was broken before the fix
  // -------------------------------------------------------------------------

  test('returns 200 when email and password are both correct', async () => {
    await register();

    const res = await request(app)
      .post('/api/login')
      .send({ email: registeredUser.email, password: registeredUser.password });

    expect(res.status).toBe(200);
  });

  test('returns a token in the response body on successful login', async () => {
    await register();

    const res = await request(app)
      .post('/api/login')
      .send({ email: registeredUser.email, password: registeredUser.password });

    // Token value is non-deterministic — assert presence only (Repeatable)
    expect(res.body.token).toBeDefined();
  });

  test('login response does not include the stored password', async () => {
    await register();

    const res = await request(app)
      .post('/api/login')
      .send({ email: registeredUser.email, password: registeredUser.password });

    expect(res.body.password).toBeUndefined();
  });

  test('same credentials work on a second consecutive login (store not mutated by login)', async () => {
    await register();

    const first = await request(app)
      .post('/api/login')
      .send({ email: registeredUser.email, password: registeredUser.password });
    expect(first.status).toBe(200);

    const second = await request(app)
      .post('/api/login')
      .send({ email: registeredUser.email, password: registeredUser.password });
    expect(second.status).toBe(200);
  });

  // -------------------------------------------------------------------------
  // Wrong-password cases — guard must still reject bad credentials
  // -------------------------------------------------------------------------

  test('returns 401 when the password is wrong', async () => {
    await register();

    const res = await request(app)
      .post('/api/login')
      .send({ email: registeredUser.email, password: 'wrong-password' });

    expect(res.status).toBe(401);
    expect(res.body.error).toBe('Invalid credentials');
  });

  test('returns 401 when the password differs only by case', async () => {
    await register();

    const res = await request(app)
      .post('/api/login')
      .send({ email: registeredUser.email, password: 'CORRECT-PASSWORD' });

    expect(res.status).toBe(401);
    expect(res.body.error).toBe('Invalid credentials');
  });

  // -------------------------------------------------------------------------
  // Unknown-email case — guard must reject an email that was never registered
  // -------------------------------------------------------------------------

  test('returns 401 when the email is not registered', async () => {
    // Store is empty — no register() call
    const res = await request(app)
      .post('/api/login')
      .send({ email: 'nobody@example.com', password: 'any-password' });

    expect(res.status).toBe(401);
    expect(res.body.error).toBe('Invalid credentials');
  });

  // -------------------------------------------------------------------------
  // Missing-field edge cases
  // -------------------------------------------------------------------------

  test('returns 401 when the email field is omitted', async () => {
    await register();

    const res = await request(app)
      .post('/api/login')
      .send({ password: registeredUser.password });

    expect(res.status).toBe(401);
    expect(res.body.error).toBe('Invalid credentials');
  });

  test('returns 401 when the password field is omitted', async () => {
    await register();

    const res = await request(app)
      .post('/api/login')
      .send({ email: registeredUser.email });

    expect(res.status).toBe(401);
    expect(res.body.error).toBe('Invalid credentials');
  });
});
