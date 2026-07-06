package br.com.rotadepico.companion.network

import br.com.rotadepico.companion.model.OfferDecisionRequest
import br.com.rotadepico.companion.model.OfferDecisionResponse
import br.com.rotadepico.companion.model.OverlayPayload
import br.com.rotadepico.companion.model.PushNotificationPayload
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject

class DecisionApiClient(
    private val baseUrl: String,
    private val bearerToken: String,
    private val httpClient: OkHttpClient = OkHttpClient()
) {

    fun analyze(requestPayload: OfferDecisionRequest): OfferDecisionResponse {
        val payload = JSONObject()
            .put("provider", requestPayload.provider)
            .put("source", requestPayload.source)
            .put("platform", requestPayload.platform)
            .put("package_name", requestPayload.packageName)
            .put("notification_title", requestPayload.notificationTitle)
            .put("notification_text", requestPayload.notificationText)
            .put("notification_received_at", requestPayload.notificationReceivedAt)
            .put("device_id", requestPayload.deviceId)
            .put("device_label", requestPayload.deviceLabel)
            .put("app_version", requestPayload.appVersion)

        val request = Request.Builder()
            .url("$baseUrl/api/mobile/listener/uber-offers/decision")
            .header("Authorization", "Bearer $bearerToken")
            .header("Accept", "application/json")
            .post(payload.toString().toRequestBody("application/json; charset=utf-8".toMediaType()))
            .build()

        httpClient.newCall(request).execute().use { response ->
            if (!response.isSuccessful) {
                error("Erro ao consultar decisao: HTTP ${response.code}")
            }

            val body = response.body?.string().orEmpty()
            val json = JSONObject(body)

            return OfferDecisionResponse(
                recommendation = json.optString("recommendation"),
                recommendationLabel = json.optString("recommendation_label"),
                decisionScore = json.optInt("decision_score"),
                riskLevel = json.optString("risk_level"),
                matchedZone = json.optString("matched_zone").ifBlank { null },
                overlay = json.optJSONObject("overlay")?.let(::parseOverlay),
                pushNotification = json.optJSONObject("push_notification")?.let(::parsePushNotification)
            )
        }
    }

    private fun parseOverlay(json: JSONObject): OverlayPayload {
        return OverlayPayload(
            show = json.optBoolean("show", true),
            tone = json.optString("tone"),
            title = json.optString("title"),
            headline = json.optString("headline"),
            label = json.optString("label"),
            message = json.optString("message"),
            score = json.optInt("score"),
            riskLevel = json.optString("risk_level"),
            matchedZone = json.optString("matched_zone").ifBlank { null },
            dismissAfterMs = json.optLong("dismiss_after_ms", 8000L),
            vibrate = json.optBoolean("vibrate", true),
            sound = json.optString("sound")
        )
    }

    private fun parsePushNotification(json: JSONObject): PushNotificationPayload {
        return PushNotificationPayload(
            title = json.optString("title"),
            body = json.optString("body")
        )
    }
}
