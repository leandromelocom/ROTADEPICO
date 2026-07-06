package br.com.rotadepico.companion.data

import android.content.Context
import br.com.rotadepico.companion.model.DecisionHistoryEntry
import br.com.rotadepico.companion.model.OfferDecisionResponse
import org.json.JSONArray
import org.json.JSONObject
import java.time.OffsetDateTime
import java.time.format.DateTimeFormatter

class DecisionHistoryRepository(context: Context) {

    private val preferences = context.getSharedPreferences("rotadepico_companion", Context.MODE_PRIVATE)

    fun saveDecision(notificationText: String, response: OfferDecisionResponse) {
        val history = loadMutable()

        history.add(
            0,
            DecisionHistoryEntry(
                happenedAt = OffsetDateTime.now().format(DateTimeFormatter.ISO_OFFSET_DATE_TIME),
                recommendationLabel = response.recommendationLabel,
                headline = response.overlay?.headline ?: response.recommendationLabel,
                message = response.overlay?.message ?: response.pushNotification?.body ?: "Analise recebida.",
                matchedZone = response.matchedZone,
                decisionScore = response.decisionScore,
                notificationText = notificationText
            )
        )

        while (history.size > MAX_HISTORY_ITEMS) {
            history.removeLast()
        }

        preferences.edit()
            .putString(KEY_HISTORY, JSONArray(history.map(::toJson)).toString())
            .apply()
    }

    fun loadHistory(): List<DecisionHistoryEntry> = loadMutable()

    private fun loadMutable(): MutableList<DecisionHistoryEntry> {
        val raw = preferences.getString(KEY_HISTORY, null).orEmpty()

        if (raw.isBlank()) {
            return mutableListOf()
        }

        return runCatching {
            val array = JSONArray(raw)
            MutableList(array.length()) { index -> fromJson(array.getJSONObject(index)) }
        }.getOrElse {
            mutableListOf()
        }
    }

    private fun toJson(entry: DecisionHistoryEntry): JSONObject {
        return JSONObject()
            .put("happened_at", entry.happenedAt)
            .put("recommendation_label", entry.recommendationLabel)
            .put("headline", entry.headline)
            .put("message", entry.message)
            .put("matched_zone", entry.matchedZone)
            .put("decision_score", entry.decisionScore)
            .put("notification_text", entry.notificationText)
    }

    private fun fromJson(json: JSONObject): DecisionHistoryEntry {
        return DecisionHistoryEntry(
            happenedAt = json.optString("happened_at"),
            recommendationLabel = json.optString("recommendation_label"),
            headline = json.optString("headline"),
            message = json.optString("message"),
            matchedZone = json.optString("matched_zone").ifBlank { null },
            decisionScore = json.optInt("decision_score"),
            notificationText = json.optString("notification_text")
        )
    }

    companion object {
        private const val KEY_HISTORY = "decision_history"
        private const val MAX_HISTORY_ITEMS = 12
    }
}
