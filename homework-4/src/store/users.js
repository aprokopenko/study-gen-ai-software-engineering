// In-memory user store — no database
let users = [];
let nextId = 1;

function addUser({ username, email, password }) {
  const user = { id: nextId++, username, email, password };
  users.push(user);
  return user;
}

function findByEmail(email) {
  return users.find((u) => u.email === email);
}

function getAllUsers() {
  return users;
}

function clearUsers() {
  users = [];
  nextId = 1;
}

module.exports = { addUser, findByEmail, getAllUsers, clearUsers };
