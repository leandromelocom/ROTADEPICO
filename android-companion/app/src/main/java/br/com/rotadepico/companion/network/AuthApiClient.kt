package br.com.rotadepico.companion.network

import br.com.rotadepico.companion.model.MobileAuthResponse
import br.com.rotadepico.companion.model.MobileUser
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject

class AuthApiClient(
    private val baseUrl: String,
    private val httpClient: OkHttpClient = OkHttpClient()
) {

    fun login(email: String, password: String): MobileAuthResponse {
        val payload = JSONObject()
            .put("email", email.trim())
            .put("password", password)

        val request = Request.Builder()
            .url("$baseUrl/api/mobile/auth/login")
            .header("Accept", "application/json")
            .post(payload.toString().toRequestBody("application/json; charset=utf-8".toMediaType()))
            .build()

        httpClient.newCall(request).execute().use { response ->
            if (!response.isSuccessful) {
                error("Falha no login mobile: HTTP ${response.code}")
            }

            val body = response.body?.string().orEmpty()
            val json = JSONObject(body)
            val userJson = json.getJSONObject("user")

            return MobileAuthResponse(
                token = json.getString("token"),
                tokenType = json.getString("token_type"),
                user = MobileUser(
                    name = userJson.optString("name"),
                    email = userJson.optString("email"),
                    city = userJson.optString("city").ifBlank { null },
                    vehicleType = userJson.optString("vehicle_type").ifBlank { null },
                    workShift = userJson.optString("work_shift").ifBlank { null },
                )
            )
        }
    }
}
