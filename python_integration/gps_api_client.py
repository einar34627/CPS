import requests
import time
from datetime import datetime

class CPSAGPSClient:
    def __init__(self, api_key="TEST_KEY_123"):
        self.base_url = "https://cpas.jampzdev.com/admin/api"
        self.api_key = api_key
        
    def get_all_units(self):
        """Get all active GPS units"""
        try:
            response = requests.get(
                f"{self.base_url}/gps_data.php",
                params={'api_key': self.api_key},
                timeout=5
            )
            return response.json()
        except Exception as e:
            print(f"Error fetching GPS data: {e}")
            return None
    
    def get_unit_by_id(self, unit_id):
        """Get specific unit by ID"""
        data = self.get_all_units()
        if data and data['success']:
            for unit in data['units']:
                if unit['id'] == unit_id:
                    return unit
        return None
    
    def get_stats(self):
        """Get only statistics"""
        data = self.get_all_units()
        return data['stats'] if data and data['success'] else None
    
    def monitor_realtime(self, interval=30):
        """Monitor GPS data in real-time"""
        print("Starting GPS Monitor...")
        print("=" * 50)
        
        while True:
            data = self.get_all_units()
            if data and data['success']:
                print(f"\n[{datetime.now().strftime('%H:%M:%S')}] Update:")
                print(f"Active: {data['stats']['active_devices']} | Total: {data['stats']['total_devices']}")
                
                for unit in data['units']:
                    print(f"  {unit['callsign']}: {unit['status']} at {unit['lat']}, {unit['lng']}")
            else:
                print(f"[{datetime.now().strftime('%H:%M:%S')}] Error fetching data")
            
            time.sleep(interval)

# Usage example
if __name__ == "__main__":
    client = CPSAGPSClient()
    
    # Test single request
    data = client.get_all_units()
    if data and data['success']:
        print(f"Connected to CPSA GPS API")
        print(f"Found {len(data['units'])} active units")
    
    # Or start real-time monitoring
    # client.monitor_realtime(interval=30)  # Every 30 seconds