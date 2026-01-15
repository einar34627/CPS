# GPS Tracking System - Complete Solution Guide

## 📋 Table of Contents
1. [System Overview](#system-overview)
2. [Quick Start Guide](#quick-start-guide)
3. [API Integration](#api-integration)
4. [Troubleshooting](#troubleshooting)
5. [Best Practices](#best-practices)

---

## 🎯 System Overview

The GPS Tracking System consists of:

### Core Components:
- **GPS Tracking Map** - Real-time visualization of all devices
- **Device Management** - Add, edit, delete GPS devices
- **Device Simulator** - Test and simulate GPS updates
- **API Endpoints** - Receive GPS data from physical devices

### Key Features:
✅ Real-time GPS tracking with auto-refresh (5 seconds)  
✅ Interactive map with color-coded status markers  
✅ Device management interface  
✅ RESTful API for device integration  
✅ GPS history tracking  
✅ Status filtering and search  

---

## 🚀 Quick Start Guide

### Step 1: Access GPS Dashboard
Navigate to: `GPS Dashboard.php`

### Step 2: Create Your First Device

**Option A: Using Device Simulator**
1. Go to `GPS Device Simulator.php`
2. Fill in the form:
   - Unit ID: `UNIT-001`
   - Callsign: `Alpha One`
   - Assignment: `Zone 1 - Coastal Road`
   - Coordinates: (defaults to Manila area)
   - Status: `On Patrol`
3. Click "Create Device"

**Option B: Using Device Management**
1. Go to `GPS Device Management.php`
2. Click "Add Device"
3. Fill in device details
4. Save

### Step 3: View on Map
1. Go to `GPS Tracking.php`
2. Your device will appear on the map
3. Click marker to see details

### Step 4: Simulate Movement
1. Go to `GPS Device Simulator.php`
2. Click "Simulate Update" on any device
3. Watch the map update in real-time

---

## 🔌 API Integration

### Endpoint URL
```
POST http://your-domain.com/CPAS/admin/api/gps_update.php
```

### Request Format
```json
{
  "device_id": "UNIT-001",
  "latitude": 14.4231,
  "longitude": 120.9724,
  "speed": 28.5,
  "battery": 92,
  "status": "On Patrol",
  "distance_today": 12.4
}
```

### Required Fields
- `device_id` (string) - Unique device identifier
- `latitude` (float) - GPS latitude (-90 to 90)
- `longitude` (float) - GPS longitude (-180 to 180)

### Optional Fields
- `speed` (float) - Speed in km/h (default: 0)
- `battery` (int) - Battery percentage 0-100 (default: 100)
- `status` (string) - Device status (default: "On Patrol")
  - Valid values: "On Patrol", "Responding", "Stationary", "Needs Assistance"
- `distance_today` (float) - Distance traveled today in km (default: 0)

### Response Format

**Success:**
```json
{
  "success": true,
  "message": "GPS data updated successfully",
  "device_id": "UNIT-001",
  "timestamp": "2025-01-15T10:30:00+08:00"
}
```

**Error:**
```json
{
  "success": false,
  "error": "device_id is required"
}
```

### Example Code

**JavaScript (Fetch API):**
```javascript
async function sendGPSUpdate(deviceId, lat, lng, speed, battery) {
    const response = await fetch('http://your-domain.com/CPAS/admin/api/gps_update.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            device_id: deviceId,
            latitude: lat,
            longitude: lng,
            speed: speed,
            battery: battery,
            status: 'On Patrol'
        })
    });
    
    const result = await response.json();
    console.log(result);
}
```

**Python:**
```python
import requests
import json

def send_gps_update(device_id, lat, lng, speed=0, battery=100):
    url = "http://your-domain.com/CPAS/admin/api/gps_update.php"
    data = {
        "device_id": device_id,
        "latitude": lat,
        "longitude": lng,
        "speed": speed,
        "battery": battery,
        "status": "On Patrol"
    }
    
    response = requests.post(url, json=data)
    return response.json()
```

**cURL:**
```bash
curl -X POST http://your-domain.com/CPAS/admin/api/gps_update.php \
  -H "Content-Type: application/json" \
  -d '{
    "device_id": "UNIT-001",
    "latitude": 14.4231,
    "longitude": 120.9724,
    "speed": 28.5,
    "battery": 92,
    "status": "On Patrol"
  }'
```

---

## 🔧 Troubleshooting

### Issue: Devices not showing on map

**Solutions:**
1. Check if device is active: `is_active = 1` in database
2. Verify coordinates are valid (latitude: -90 to 90, longitude: -180 to 180)
3. Check browser console for JavaScript errors
4. Ensure map library (Leaflet) is loading correctly
5. Try refreshing the page

### Issue: API endpoint not working

**Solutions:**
1. Verify endpoint URL is correct
2. Check CORS headers if calling from different domain
3. Ensure Content-Type header is `application/json`
4. Verify database connection is working
5. Check PHP error logs

### Issue: Real-time updates not working

**Solutions:**
1. Check browser console for errors
2. Verify `api/gps_data.php` is accessible
3. Check network tab for failed requests
4. Ensure session is valid (logged in)
5. Check database connection

### Issue: Copy to clipboard not working

**Solutions:**
1. Ensure you're using HTTPS or localhost (browser security requirement)
2. Check browser permissions for clipboard access
3. Try manual copy if clipboard API fails
4. Use the toast notification to verify copy status

### Issue: Device status not updating

**Solutions:**
1. Verify status value matches exactly: "On Patrol", "Responding", "Stationary", "Needs Assistance"
2. Check database for status updates
3. Clear browser cache
4. Check API response for errors

---

## 💡 Best Practices

### 1. Device Naming Convention
- Use consistent Unit IDs: `UNIT-001`, `UNIT-002`, etc.
- Use descriptive callsigns: `Alpha One`, `Bravo Two`, etc.
- Assign clear zone/area names

### 2. GPS Update Frequency
- **Recommended:** Update every 5-30 seconds for active devices
- **Minimum:** Update every 60 seconds to maintain "live" status
- **Maximum:** Avoid updates more frequent than every 2 seconds

### 3. Battery Management
- Monitor battery levels regularly
- Set alerts for low battery (< 20%)
- Update battery status with each GPS ping

### 4. Status Management
- Use "On Patrol" for normal operations
- Use "Responding" when en route to an incident
- Use "Stationary" when parked/stopped
- Use "Needs Assistance" for emergencies

### 5. Data Validation
- Always validate coordinates before sending
- Check speed values are reasonable (0-200 km/h)
- Ensure battery is between 0-100%
- Validate status values

### 6. Error Handling
- Implement retry logic for failed API calls
- Log errors for debugging
- Handle network timeouts gracefully
- Display user-friendly error messages

### 7. Security
- Use HTTPS in production
- Implement API authentication if needed
- Validate all input data
- Sanitize device IDs to prevent SQL injection

---

## 📊 Database Schema

### gps_units Table
```sql
- id (INT, PRIMARY KEY)
- unit_id (VARCHAR(50), UNIQUE)
- callsign (VARCHAR(100))
- assignment (VARCHAR(255))
- latitude (DECIMAL(10,8))
- longitude (DECIMAL(11,8))
- status (ENUM)
- speed (DECIMAL(5,2))
- battery (INT)
- distance_today (DECIMAL(8,2))
- last_ping (TIMESTAMP)
- is_active (TINYINT(1))
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)
```

### gps_history Table
```sql
- id (INT, PRIMARY KEY)
- unit_id (VARCHAR(50))
- latitude (DECIMAL(10,8))
- longitude (DECIMAL(11,8))
- speed (DECIMAL(5,2))
- recorded_at (TIMESTAMP)
```

---

## 🎨 Status Colors

- **On Patrol** - Blue (#1d4ed8)
- **Responding** - Green (#16a34a)
- **Stationary** - Grey (#94a3b8)
- **Needs Assistance** - Red (#dc2626) with pulse animation

---

## 📱 Mobile Integration

For mobile apps, use the same API endpoint:

```javascript
// React Native / Expo
const sendGPSUpdate = async (deviceId, location) => {
    try {
        const response = await fetch('YOUR_API_URL', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                device_id: deviceId,
                latitude: location.coords.latitude,
                longitude: location.coords.longitude,
                speed: location.coords.speed || 0,
                battery: getBatteryLevel(), // Your battery function
                status: 'On Patrol'
            })
        });
        return await response.json();
    } catch (error) {
        console.error('GPS update failed:', error);
    }
};
```

---

## 🔄 Auto-Refresh System

The GPS Tracking page automatically refreshes every 5 seconds:
- Fetches latest data from `api/gps_data.php`
- Updates map markers smoothly
- Updates statistics
- Shows connection status

To change refresh interval, modify in `GPS Tracking.php`:
```javascript
updateInterval = setInterval(fetchGPSData, 5000); // 5000ms = 5 seconds
```

---

## 📞 Support

For issues or questions:
1. Check this guide first
2. Review browser console for errors
3. Check PHP error logs
4. Verify database connectivity
5. Test API endpoint using the built-in test tool

---

## ✅ Checklist for New Device Setup

- [ ] Device created in system
- [ ] Unit ID is unique
- [ ] Initial coordinates set
- [ ] Status configured
- [ ] Device appears on map
- [ ] API endpoint tested
- [ ] Real-time updates working
- [ ] Battery monitoring enabled

---

**Last Updated:** January 2025  
**Version:** 1.0

