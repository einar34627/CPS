import requests
import json

# GPS API Configuration
API_URL = "https://cpas.jampzdev.com/admin/api/gps_data.php"
API_KEY = "TEST_KEY_123"

def get_gps_data():
    """Fetch GPS data from the API"""
    try:
        response = requests.get(
            f"{API_URL}?api_key={API_KEY}",
            timeout=10
        )
        
        if response.status_code == 200:
            data = response.json()
            return data
        else:
            print(f"Error: HTTP {response.status_code}")
            return None
            
    except Exception as e:
        print(f"Connection error: {e}")
        return None

# Run the test
if __name__ == "__main__":
    print("Testing GPS API...")
    data = get_gps_data()
    
    if data and data.get('success'):
        print("✓ API Working!")
        print(f"Active units: {data['stats']['active_devices']}")
        
        for unit in data['units']:
            print(f"  - {unit['callsign']}: {unit['status']}")
    else:
        print("✗ API Failed")
        print(data)