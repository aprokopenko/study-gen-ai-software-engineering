/**
 * Tests for the duplicate-email guard introduced in the Bug 001 fix.
 *
 * Only behaviour changed in src/routes/auth.js is covered here.
 * The in-memory store is reset before every test via clearUsers() so each
 * test starts from an empty state (Independent, Repeatable).
 */
const request = require('supertest');
const app = require('../src/app');
const { clearUsers } = require('../src/store/users');

beforeEach(() => clearUsers());

describe('POST /api/register — duplicate-email guard (Bug 001 fix)', () => {
  const validUser = {
    username: 'alice',
    email: 'alice@example.com',
    password: 'secret123',
  };

  // --- Happy-path regression: fix must not break normal registration ---

  test('registers a new user and returns 201 with correct body shape', async () => {
    const res = await request(app).post('/api/register').send(validUser);

    expect(res.status).toBe(201);
    expect(res.body.user.id).toBeDefined();
    expect(res.body.user.username).toBe(validUser.username);
    expect(res.body.user.email).toBe(validUser.email);
    // password must not be leaked in the response
    expect(res.body.user.password).toBeUndefined();
    // token is present (value is non-deterministic, so only its presence is asserted)
    expect(res.body.token).toBeDefined();
  });

  // --- Core fix: duplicate email on second registration attempt ---

  test('returns 409 when the same email is registered a second time', async () => {
    // First registration succeeds
    await request(app).post('/api/register').send(validUser);

    // Second registration with identical email
    const res = await request(app).post('/api/register').send({
      username: 'another-alice',
      email: validUser.email, // same email, different username
      password: 'different-password',
    });

    expect(res.status).toBe(409);
    expect(res.body.error).toBe('Email already in use');
  });

  test('returns 409 even when only the email matches (username differs)', async () => {
    await request(app).post('/api/register').send(validUser);

    const res = await request(app).post('/api/register').send({
      username: 'bob',
      email: validUser.email,
      password: 'pass456',
    });

    expect(res.status).toBe(409);
  });

  // --- Fix must not prevent a *different* email from registering ---

  test('allows a second registration with a different email (returns 201)', async () => {
    await request(app).post('/api/register').send(validUser);

    const res = await request(app).post('/api/register').send({
      username: 'bob',
      email: 'bob@example.com', // different email
      password: 'pass456',
    });

    expect(res.status).toBe(201);
    expect(res.body.user.email).toBe('bob@example.com');
  });

  // --- Regression: field-validation guard (400) must still fire BEFORE duplicate check ---

  test('returns 400 when email field is missing (validation runs before duplicate check)', async () => {
    const res = await request(app).post('/api/register').send({
      username: 'charlie',
      // email intentionally omitted
      password: 'pass789',
    });

    expect(res.status).toBe(400);
    expect(res.body.error).toBe('All fields required');
  });

  test('returns 400 when username field is missing', async () => {
    const res = await request(app).post('/api/register').send({
      // username intentionally omitted
      email: 'charlie@example.com',
      password: 'pass789',
    });

    expect(res.status).toBe(400);
    expect(res.body.error).toBe('All fields required');
  });

  test('returns 400 when password field is missing', async () => {
    const res = await request(app).post('/api/register').send({
      username: 'charlie',
      email: 'charlie@example.com',
      // password intentionally omitted
    });

    expect(res.status).toBe(400);
    expect(res.body.error).toBe('All fields required');
  });

  // --- Edge case: email comparison is exact / case-sensitive ---
  // findByEmail uses Array.find with strict equality, so uppercase variants
  // of the same address are treated as distinct — the fix must not change that.

  test('treats email addresses as case-sensitive (different case is not a duplicate)', async () => {
    await request(app).post('/api/register').send(validUser); // alice@example.com

    const res = await request(app).post('/api/register').send({
      username: 'alice-upper',
      email: 'Alice@example.com', // different casing
      password: 'secret123',
    });

    // Current behaviour: case-sensitive match, so this is NOT a duplicate
    expect(res.status).toBe(201);
  });

  // --- Idempotency of the guard: third attempt is also rejected ---

  test('continues to return 409 on every subsequent attempt with the same email', async () => {
    await request(app).post('/api/register').send(validUser);

    // Second attempt
    const second = await request(app)
      .post('/api/register')
      .send({ username: 'dup2', email: validUser.email, password: 'pw' });
    expect(second.status).toBe(409);

    // Third attempt
    const third = await request(app)
      .post('/api/register')
      .send({ username: 'dup3', email: validUser.email, password: 'pw' });
    expect(third.status).toBe(409);
  });
});
