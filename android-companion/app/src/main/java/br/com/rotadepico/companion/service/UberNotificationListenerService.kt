package br.com.rotadepico.companion.service

import android.service.notification.NotificationListenerService
import android.service.notification.StatusBarNotification
import android.util.Log
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

    override fun onListenerConnected() {
        super.onListenerConnected()
        Log.i(TAG, "Listener conectado - pronto para ler notificacoes de $UBER_DRIVER_PACKAGE")
    }

    override fun onNotificationPosted(sbn: StatusBarNotification) {
        if (sbn.packageName != UBER_DRIVER_PACKAGE) {
            return
        }

        Log.d(TAG, "Notificacao recebida de $UBER_DRIVER_PACKAGE")

        val extras = sbn.notification.extras
        if (extras == null) {
            Log.w(TAG, "Notificacao sem extras, ignorada")
            return
        }

        val title = extras.getCharSequence("android.title")?.toString()
        val text = (extras.getCharSequence("android.bigText") ?: extras.getCharSequence("android.text"))
            ?.toString()?.trim().orEmpty()

        if (text.isBlank()) {
            Log.w(TAG, "Notificacao da Uber sem texto legivel (android.text/android.bigText vazios), ignorada")
            return
        }

        val dedupeKey = "${sbn.packageName}:${text}"
        val now = System.currentTimeMillis()
        val lastSeen = recentPayloads[dedupeKey]

        if (lastSeen != null && now - lastSeen < 2500) {
            Log.d(TAG, "Notificacao duplicada dentro de 2,5s, ignorada")
            return
        }

        recentPayloads[dedupeKey] = now

        val settings = SettingsRepository(applicationContext)

        if (settings.apiBaseUrl().isBlank() || settings.bearerToken().isBlank()) {
            Log.w(TAG, "Sem URL/token configurado - faca login no app antes de ficar online na Uber")
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

                client.analyze(requestPayload)
            }.onSuccess { decision ->
                DecisionHistoryRepository(applicationContext).saveDecision(text, decision)
                DecisionOverlayPresenter(applicationContext).show(decision)
            }.onFailure { throwable ->
                Log.e(TAG, "Falha ao analisar oferta da Uber", throwable)
                DecisionOverlayPresenter(applicationContext)
                    .showFallback(throwable.message ?: "erro desconhecido")
            }
        }
    }

    override fun onDestroy() {
        scope.cancel()
        super.onDestroy()
    }

    companion object {
        private const val TAG = "RotaDePicoListener"
        private const val UBER_DRIVER_PACKAGE = "com.ubercab.driver"
    }
}
