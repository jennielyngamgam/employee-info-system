const express = require('express');
const app = express();

app.use(express.json());

// sample database (temporary lang)
let employees = [
    { id: 1, name: "Juan Dela Cruz", position: "Manager" },
    { id: 2, name: "Maria Santos", position: "Staff" }
];

// GET all employees
app.get('/employees', (req, res) => {
    res.json(employees);
});

// GET single employee
app.get('/employees/:id', (req, res) => {
    const emp = employees.find(e => e.id == req.params.id);
    if (!emp) return res.status(404).send("Employee not found");
    res.json(emp);
});

// ADD employee
app.post('/employees', (req, res) => {
    const newEmp = {
        id: employees.length + 1,
        name: req.body.name,
        position: req.body.position
    };
    employees.push(newEmp);
    res.json(newEmp);
});

// UPDATE employee
app.put('/employees/:id', (req, res) => {
    const emp = employees.find(e => e.id == req.params.id);
    if (!emp) return res.status(404).send("Not found");

    emp.name = req.body.name;
    emp.position = req.body.position;

    res.json(emp);
});

// DELETE employee
app.delete('/employees/:id', (req, res) => {
    employees = employees.filter(e => e.id != req.params.id);
    res.send("Deleted");
});

app.listen(3000, () => {
    console.log("Server running on port 3000");
});