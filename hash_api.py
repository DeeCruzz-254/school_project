from flask import Flask, request, jsonify
from argon2 import PasswordHasher
from flask_cors import CORS

app = Flask(__name__)
CORS(app)  # allow PHP requests from different origin

ph = PasswordHasher()

@app.route("/hash", methods=["POST"])
def hash_password():
    data = request.get_json()

    if not data or "password" not in data:
        return jsonify({"error": "Password is required"}), 400

    try:
        hashed = ph.hash(data["password"])
        return jsonify({"hash": hashed})
    except Exception as e:
        return jsonify({"error": str(e)}), 500

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000)
