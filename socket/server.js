const express = require('express');
const app = express();
const http = require('http');
const server = http.createServer(app);
const { Server } = require("socket.io");
const io = new Server(server, {
    cors: { origin: "*" }
});

app.use(express.json());

// Notify endpoint for PHP to trigger updates
app.post('/notify', (req, res) => {
    const { type, data } = req.body;
    io.emit(type, data);
    res.send({ status: 'success' });
});

io.on('connection', (socket) => {
    console.log('Un client est connecté');
});

server.listen(3000, () => {
    console.log('Socket server running on port 3000');
});
