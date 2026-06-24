const express = require('express');
const authRoutes = require('./routes/auth');

const app = express();
app.use(express.json());
app.use('/api', authRoutes);

module.exports = app;
