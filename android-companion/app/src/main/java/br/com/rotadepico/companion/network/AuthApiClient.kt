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

        return execute("$baseUrl/api/mobile/auth/login", payload, "Falha no login mobile")
    }

    fun register(
        name: String,
        email: String,
        phone: String,
        city: String,
        vehicleType: String,
        workShift: String,
        password: String,
        passwordConfirmation: String
    ): MobileAuthResponse {
        val payload = JSONObject()
            .put("name", name.trim())
            .put("email", email.trim())
            .put("phone", phone.trim())
            .put("city", city.trim())
            .put("vehicle_type", vehicleType)
            .put("work_shift", workShift)
            .put("password", password)
            .put("password_confirmation", passwordConfirmation)

        return execute("$baseUrl/api/mobile/auth/register", payload, "Falha no cadastro")
    }

    private fun execute(url: String, payload: JSONObject, failureLabel: String): MobileAuthResponse {
        val request = Request.Builder()
            .url(url)
            .header("Accept", "application/json")
            .post(payload.toString().toRequestBody("application/json; charset=utf-8".toMediaType()))
            .build()

        httpClient.newCall(request).execute().use { response ->
            val body = response.body?.string().orEmpty()

            if (!response.isSuccessful) {
                error(extractErrorMessage(body) ?: "$failureLabel: HTTP ${response.code}")
            }

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

    private fun extractErrorMessage(body: String): String? = runCatching {
        val json = JSONObject(body)
        val errors = json.optJSONObject("errors")
        val firstFieldMessage = errors?.keys()?.asSequence()
            ?.mapNotNull { key -> errors.optJSONArray(key)?.optString(0) }
            ?.firstOrNull { it.isNotBlank() }

        firstFieldMessage ?: json.optString("message").ifBlank { null }
    }.getOrNull()
}
