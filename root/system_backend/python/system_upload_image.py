from flask import Flask, request, jsonify
import os

app = Flask(__name__)

@app.route('/analyze', methods=['POST'])
def analyze():
    if 'image' not in request.files:
        return jsonify({'error': 'No image uploaded'}), 400
    
    image = request.files['image']
    image_path = os.path.join(os.getcwd(), 'uploads', image.filename)
    image.save(image_path)
    
    # Simulate analysis
    return jsonify({
        'status': 'success',
        'message': f'Image {image.filename} analyzed successfully!',
        'path': image_path
    })

if __name__ == '__main__':
    app.run(host='127.0.0.1', port=5000)
