# FinZ LMS - Phase 4 APIs (Merchant Lifecycle)

**Base URL:** `http://127.0.0.1:8000/api/v1`
**Authorization:** All endpoints require a Bearer Token (JWT) and MFA verification.
**Headers:** `Authorization: Bearer <your_token>`

---

### 1. Get Merchant Directory (Screen 14)
**Endpoint:** `GET /admin/merchants`
**Description:** Fetches a paginated list of all merchants with their workflow status.
**Query Parameters (Optional):**
- `status` (String) - Filter by status (Draft, Submitted, Under Review, Approved, Rejected, Re-KYC, Suspended)
**Response:**
```json
{
    "current_page": 1,
    "data": [
        {
            "id": 1,
            "business_name": "Acme Corp",
            "status": "Approved",
            "store_count": 5,
            "disbursal_volume": "150000.00"
        }
    ],
    "total": 1
}
```

---

### 2. Get Merchant 360 Profile (Screen 16)
**Endpoint:** `GET /admin/merchants/{id}`
**Description:** Fetches the complete profile and details of a specific merchant.
**Path Variables:**
- `id` (Integer) - The Merchant ID

---

### 3. Approve Merchant (Screen 15)
**Endpoint:** `POST /admin/merchants/{id}/approve`
**Description:** Approves a merchant after KYC review.
**Body (JSON):**
```json
{
    "comment": "All documents verified."
}
```

---

### 4. Reject Merchant (Screen 15)
**Endpoint:** `POST /admin/merchants/{id}/reject`
**Description:** Rejects a merchant onboarding application.
**Body (JSON):**
```json
{
    "reason": "GST number mismatch."
}
```

---

### 5. Trigger Re-KYC (Screen 19)
**Endpoint:** `POST /admin/merchants/{id}/re-kyc`
**Description:** Triggers the Re-KYC workflow for an existing merchant.
**Body (JSON):**
```json
{
    "reason": "Annual compliance check required."
}
```

---

### 6. Suspend Merchant (Screen 19)
**Endpoint:** `POST /admin/merchants/{id}/suspend`
**Description:** Suspends a merchant and auto-disables their stores/logins.
**Body (JSON):**
```json
{
    "reason": "High NPA detected."
}
```

---

### 7. Get Verification API Logs (Screen 18)
**Endpoint:** `GET /admin/merchants/{id}/verification-logs`
**Description:** Fetches the raw third-party verification logs (GST, PAN, Aadhaar) for the merchant.
**Query Parameters (Optional):**
- `api_type` (String) - e.g., 'GST', 'PAN', 'Bank'

---

### 8. Generate Merchant Agreement (Screen 17)
**Endpoint:** `POST /admin/merchants/{id}/agreement`
**Description:** Generates a PDF agreement and initializes the eSign tracking.
**Response:**
```json
{
    "message": "Agreement generated successfully",
    "agreement": {
        "id": 1,
        "merchant_id": 10,
        "status": "Generated",
        "version": 1
    }
}
```