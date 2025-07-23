# hash_api.py
from flask import Flask, request, jsonify
from flask_cors import CORS
from argon2 import PasswordHasher

app = Flask(__name__)
CORS(app)  # Allows requests from your PHP frontend

ph = PasswordHasher()

@app.route('/hash', methods=['POST'])
def hash_password():
    data = request.get_json()
    if not data or 'password' not in data:
        return jsonify({'error': 'Password is required'}), 400

    try:
        hashed = ph.hash(data['password'])
        return jsonify({'hashed_password': hashed}), 200
    except Exception as e:
        return jsonify({'error': str(e)}), 500

if __name__ == '__main__':
    app.run(debug=True)
