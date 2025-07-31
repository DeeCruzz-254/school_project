from flask import Flask, request
import mysql.connector

# Connect to the database
connection = mysql.connector.connect(
    host='localhost',
    user='root',
    password='password123',
    database='login_form'
)

cursor = connection.cursor()

# Confirm connection and database
cursor.execute("SELECT DATABASE();")
db_name = cursor.fetchone()
print("📦 Currently using database:", db_name[0])

# Create table and insert data
cursor.execute("""
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255),
    password VARCHAR(255)
)
""")
cursor.execute("INSERT INTO users (username, password) VALUES (%s, %s)", ('sila', 'secret'))
cursor.execute("INSERT INTO users (username, password) VALUES (%s, %s)", ('user', 'pass123'))
cursor.execute("INSERT INTO users (username, password) VALUES (%s, %s)", ('guest', 'guestpass'))
connection.commit()

# Print users for confirmation
cursor.execute("SELECT * FROM Users")
for row in cursor.fetchall():
    print(row)

# Flask app setup
app = Flask(__name__)

@app.route('/login', methods=['POST'])
def login_handler():
    username = request.form.get('username')
    password = request.form.get('password') 
    if username == 'admin' and password == 'secret':
        return "Login successful", 200  
    else:
        return "Invalid credentials", 401   

# Start Flask server
if __name__ == '__main__':
    app.run(debug=True)
