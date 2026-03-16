#!/usr/bin/python3
from flask import Flask, redirect, request, render_template, url_for

# Create Flask instance
app = Flask(__name__)

# Hardcoded credentials
name = "MrRobot"
password_real = "password"
valid_name = name.lower()
valid_password = password_real.lower()

@app.route('/')
def index():
    return render_template('login.html')

@app.route('/login', methods=['POST'])
def login():
    username = request.form.get('username', '').lower()
    password = request.form.get('password', '').lower()

    if username == valid_name and password == valid_password:
        return redirect(url_for('portal'))  # redirect naar de portal route
    else:
        return "Login failed"

@app.route('/portal')
def portal():
    return render_template('portal.html')  # render portal.html

if __name__ == "__main__":
    app.run(debug=True)