package br.com.rotadepico.companion.service

import android.service.notification.NotificationListenerService
import android.service.notification.StatusBarNotification
import br.com.rotadepico.companion.data.SettingsRepository
import br.com.rotadepico.companion.model.OfferDecisionRequest
import br.com.rotadepico.companion.network.DecisionApiClient
import android.os.Build
import br.com.rotadepico.companion.BuildConfig
import br.com.rotadepico.companion.data.DecisionHistoryRepository
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.launch
import java.time.OffsetDateTime
import java.util.concurrent.ConcurrentHashMap

class UberNotificationListenerService : NotificationListenerService() {

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private val recentPayloads = ConcurrentHashMap<String, Long>()

    override fun onNotificationPosted(sbn: StatusBarNotification) {
        if (sbn.packageName != UBER_DRIVER_PACKAGE) {
            return
        }

        val extras = sbn.notification.extras ?: return
        val title = extras.getCharSequence("android.title")?.toString()
        val text = extras.getCharSequence("android.text")?.toString()?.trim().orEmpty()

        if (text.isBlank()) {
            return
        }

        val dedupeKey = "${sbn.packageName}:${text}"
        val now = System.currentTimeMillis()
        val lastSeen = recentPayloads[dedupeKey]

        if (lastSeen != null && now - lastSeen < 2500) {
            return
        }

        recentPayloads[dedupeKey] = now

        val settings = SettingsRepository(applicationContext)

        if (settings.apiBaseUrl().isBlank() || settings.bearerToken().isBlank()) {
            return
        }

        val requestPayload = OfferDecisionRequest(
            provider = "uber",
            source = "notification_listener",
            platform = "android",
            packageName = sbn.packageName,
            notificationTitle = title,
            notificationText = text,
            notificationReceivedAt = OffsetDateTime.now().toString(),
            deviceId = settings.deviceId(),
            deviceLabel = "${Build.MANUFACTURER} ${Build.MODEL}".trim(),
            appVersion = BuildConfig.VERSION_NAME
        )

        scope.launch {
            runCatching {
                val client = DecisionApiClient(
                    baseUrl = settings.apiBaseUrl(),
                    bearerToken = settings.bearerToken()
                )

                val decision = client.analyze(requestPayload)
                DecisionHistoryRepository(applicationContext).saveDecision(text, decision)
                DecisionOverlayPresenter(applicationContext).show(decision)
            }
        }
    }

    override fun onDestroy() {
        scope.cancel()
        super.onDestroy()
    }

    companion object {
        private const val UBER_DRIVER_PACKAGE = "com.ubercab.driver"
    }
}
