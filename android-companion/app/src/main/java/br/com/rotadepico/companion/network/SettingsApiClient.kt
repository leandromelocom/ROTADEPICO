package br.com.rotadepico.companion.network

import br.com.rotadepico.companion.model.CostSettings
import br.com.rotadepico.companion.model.DecisionSettings
import br.com.rotadepico.companion.model.DriverSettings
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject

class SettingsApiClient(
    private val baseUrl: String,
    private val bearerToken: String,
    private val httpClient: OkHttpClient = OkHttpClient()
) {

    fun fetch(): DriverSettings {
        val request = Request.Builder()
            .url("$baseUrl/api/mobile/settings")
            .header("Accept", "application/json")
            .header("Authorization", "Bearer $bearerToken")
            .get()
            .build()

        val json = execute(request)

        return DriverSettings(
            decisionSettings = parseDecisionSettings(json.getJSONObject("decision_settings")),
            costSettings = parseCostSettings(json.getJSONObject("cost_settings"))
        )
    }

    fun updateDecisionSettings(settings: DecisionSettings): DecisionSettings {
        val payload = JSONObject()
            .put("decision_profile", settings.decisionProfile)
            .put("min_offer_fare", settings.minOfferFare)
            .put("min_fare_per_km", settings.minFarePerKm)
            .put("min_hourly_rate", settings.minHourlyRate)
            .put("max_pickup_distance_km", settings.maxPickupDistanceKm)
            .put("max_pickup_eta_minutes", settings.maxPickupEtaMinutes)

        val request = Request.Builder()
            .url("$baseUrl/api/mobile/settings/decision")
            .header("Accept", "application/json")
            .header("Authorization", "Bearer $bearerToken")
            .patch(payload.toString().toRequestBody("application/json; charset=utf-8".toMediaType()))
            .build()

        return parseDecisionSettings(execute(request).getJSONObject("decision_settings"))
    }

    fun updateCostSettings(settings: CostSettings): CostSettings {
        val payload = JSONObject()
            .put("fuel_consumption_km_per_l", settings.fuelConsumptionKmPerL)
            .put("fuel_price_per_liter", settings.fuelPricePerLiter)
            .put("extra_cost_per_km", settings.extraCostPerKm)

        val request = Request.Builder()
            .url("$baseUrl/api/mobile/settings/cost")
            .header("Accept", "application/json")
            .header("Authorization", "Bearer $bearerToken")
            .patch(payload.toString().toRequestBody("application/json; charset=utf-8".toMediaType()))
            .build()

        return parseCostSettings(execute(request).getJSONObject("cost_settings"))
    }

    private fun execute(request: Request): JSONObject {
        httpClient.newCall(request).execute().use { response ->
            val body = response.body?.string().orEmpty()

            if (!response.isSuccessful) {
                error(extractErrorMessage(body) ?: "Falha na comunicacao: HTTP ${response.code}")
            }

            return JSONObject(body)
        }
    }

    private fun parseDecisionSettings(json: JSONObject) = DecisionSettings(
        decisionProfile = json.optString("decision_profile", "equilibrado"),
        minOfferFare = json.optDouble("min_offer_fare", 0.0),
        minFarePerKm = json.optDouble("min_fare_per_km", 0.0),
        minHourlyRate = json.optDouble("min_hourly_rate", 0.0),
        maxPickupDistanceKm = json.optDouble("max_pickup_distance_km", 0.0),
        maxPickupEtaMinutes = json.optInt("max_pickup_eta_minutes", 0)
    )

    private fun parseCostSettings(json: JSONObject) = CostSettings(
        fuelConsumptionKmPerL = json.optDouble("fuel_consumption_km_per_l", 0.0),
        fuelPricePerLiter = json.optDouble("fuel_price_per_liter", 0.0),
        extraCostPerKm = json.optDouble("extra_cost_per_km", 0.0)
    )

    private fun extractErrorMessage(body: String): String? = runCatching {
        val json = JSONObject(body)
        val errors = json.optJSONObject("errors")
        val firstFieldMessage = errors?.keys()?.asSequence()
            ?.mapNotNull { key -> errors.optJSONArray(key)?.optString(0) }
            ?.firstOrNull { it.isNotBlank() }

        firstFieldMessage ?: json.optString("message").ifBlank { null }
    }.getOrNull()
}
