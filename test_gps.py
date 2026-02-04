import requests
url = "https://cpas.jampzdev.com/admin/api/gps_data.php?api_key=TEST_KEY_123"
response = requests.get(url)
print(response.json())