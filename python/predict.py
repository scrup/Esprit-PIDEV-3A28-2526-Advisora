import sys
import json
import joblib
import pandas as pd

# Load models once (done at script start)
model = joblib.load('python/strategy_model.pkl')
scaler = joblib.load('python/scaler.pkl')
label_encoders = joblib.load('python/label_encoders.pkl')
target_encoder = joblib.load('python/target_encoder.pkl')
feature_columns = joblib.load('python/feature_columns.pkl')

def predict(data):
    df = pd.DataFrame([data])
    # Apply same preprocessing as training
    for col, le in label_encoders.items():
        df[col] = le.transform(df[col])
    numerical_cols = scaler.feature_names_in_
    df[numerical_cols] = scaler.transform(df[numerical_cols])
    df = df[feature_columns]
    
    pred_encoded = model.predict(df)[0]
    strategy = target_encoder.inverse_transform([pred_encoded])[0]
    return strategy

if __name__ == "__main__":
    # Read JSON from command line argument or stdin
    if len(sys.argv) > 1:
        input_json = sys.argv[1]
    else:
        input_json = sys.stdin.read()
    
    project_data = json.loads(input_json)
    result = predict(project_data)
    print(result)  # Symfony will capture this output