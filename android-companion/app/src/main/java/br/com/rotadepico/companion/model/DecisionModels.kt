package br.com.rotadepico.companion.model

data class OfferDecisionRequest(
    val provider: String,
    val source: String,
    val platform: String,
    val packageName: String,
    val notificationTitle: String?,
    val notificationText: String,
    val notificationReceivedAt: String,
    val deviceId: String,
    val deviceLabel: String?,
    val appVersion: String
)

data class OfferDecisionResponse(
    val recommendation: String,
    val recommendationLabel: String,
    val decisionScore: Int,
    val riskLevel: String,
    val matchedZone: String?,
    val overlay: OverlayPayload?,
    val pushNotification: PushNotificationPayload?
)

data class OverlayPayload(
    val show: Boolean,
    val tone: String,
    val title: String,
    val headline: String,
    val label: String,
    val message: String,
    val score: Int,
    val riskLevel: String,
    val matchedZone: String?,
    val dismissAfterMs: Long,
    val vibrate: Boolean,
    val sound: String
)

data class PushNotificationPayload(
    val title: String,
    val body: String
)

data class DecisionHistoryEntry(
    val happenedAt: String,
    val recommendationLabel: String,
    val headline: String,
    val message: String,
    val matchedZone: String?,
    val decisionScore: Int,
    val notificationText: String
)
