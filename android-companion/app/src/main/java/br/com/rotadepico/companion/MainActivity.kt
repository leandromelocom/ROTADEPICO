package br.com.rotadepico.companion

import android.Manifest
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.provider.Settings
import android.view.View
import android.widget.Button
import android.widget.EditText
import android.widget.LinearLayout
import android.widget.TextView
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import br.com.rotadepico.companion.data.DecisionHistoryRepository
import br.com.rotadepico.companion.data.SettingsRepository
import br.com.rotadepico.companion.model.DecisionHistoryEntry
import br.com.rotadepico.companion.network.AuthApiClient
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.time.OffsetDateTime
import java.time.format.DateTimeFormatter

class MainActivity : AppCompatActivity() {

    private lateinit var settingsRepository: SettingsRepository
    private lateinit var historyRepository: DecisionHistoryRepository
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.Main)

    private val notificationsPermissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { updateStatus() }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        settingsRepository = SettingsRepository(this)
        historyRepository = DecisionHistoryRepository(this)

        val apiBaseUrl = findViewById<EditText>(R.id.apiBaseUrlField)
        val loginEmail = findViewById<EditText>(R.id.loginEmailField)
        val loginPassword = findViewById<EditText>(R.id.loginPasswordField)
        val bearerToken = findViewById<EditText>(R.id.bearerTokenField)
        val deviceId = findViewById<EditText>(R.id.deviceIdField)
        val loginButton = findViewById<Button>(R.id.loginButton)
        val saveButton = findViewById<Button>(R.id.saveSettingsButton)
        val notificationAccessButton = findViewById<Button>(R.id.notificationAccessButton)
        val overlayAccessButton = findViewById<Button>(R.id.overlayAccessButton)
        val notificationPermissionButton = findViewById<Button>(R.id.notificationPermissionButton)

        apiBaseUrl.setText(settingsRepository.apiBaseUrl())
        bearerToken.setText(settingsRepository.bearerToken())
        deviceId.setText(settingsRepository.deviceId())
        loginEmail.setText(settingsRepository.userEmail())

        loginButton.setOnClickListener {
            findViewById<TextView>(R.id.authStatusValue).text = getString(R.string.auth_status_loading)

            scope.launch {
                runCatching {
                    withContext(Dispatchers.IO) {
                        AuthApiClient(apiBaseUrl.text.toString().ifBlank { settingsRepository.apiBaseUrl() })
                            .login(
                                email = loginEmail.text.toString(),
                                password = loginPassword.text.toString()
                            )
                    }
                }.onSuccess { auth ->
                    settingsRepository.saveAuthSession(
                        apiBaseUrl = apiBaseUrl.text.toString().ifBlank { settingsRepository.apiBaseUrl() },
                        bearerToken = auth.token,
                        deviceId = deviceId.text.toString().ifBlank { settingsRepository.deviceId() },
                        userName = auth.user.name,
                        userEmail = auth.user.email
                    )

                    bearerToken.setText(auth.token)
                    findViewById<TextView>(R.id.authStatusValue).text =
                        getString(R.string.auth_status_ready, auth.user.name)
                    updateStatus()
                }.onFailure {
                    findViewById<TextView>(R.id.authStatusValue).text = getString(R.string.auth_status_error)
                }
            }
        }

        saveButton.setOnClickListener {
            settingsRepository.save(
                apiBaseUrl = apiBaseUrl.text.toString(),
                bearerToken = bearerToken.text.toString(),
                deviceId = deviceId.text.toString()
            )
            updateStatus()
        }

        notificationAccessButton.setOnClickListener {
            startActivity(Intent(Settings.ACTION_NOTIFICATION_LISTENER_SETTINGS))
        }

        overlayAccessButton.setOnClickListener {
            val intent = Intent(
                Settings.ACTION_MANAGE_OVERLAY_PERMISSION,
                Uri.parse("package:$packageName")
            )
            startActivity(intent)
        }

        notificationPermissionButton.setOnClickListener {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                notificationsPermissionLauncher.launch(Manifest.permission.POST_NOTIFICATIONS)
            }
        }

        updateStatus()
    }

    override fun onResume() {
        super.onResume()
        updateStatus()
    }

    private fun updateStatus() {
        findViewById<TextView>(R.id.endpointStatusValue).text = settingsRepository.apiBaseUrl()
        findViewById<TextView>(R.id.authStatusValue).text =
            if (settingsRepository.userName().isBlank()) getString(R.string.status_pending)
            else getString(R.string.auth_status_ready, settingsRepository.userName())
        findViewById<TextView>(R.id.tokenStatusValue).text =
            if (settingsRepository.bearerToken().isBlank()) getString(R.string.status_pending) else getString(R.string.status_ready)
        findViewById<TextView>(R.id.listenerStatusValue).text =
            if (settingsRepository.hasNotificationAccess()) getString(R.string.status_ready) else getString(R.string.status_pending)
        findViewById<TextView>(R.id.overlayStatusValue).text =
            if (Settings.canDrawOverlays(this)) getString(R.string.status_ready) else getString(R.string.status_pending)

        renderHistory(historyRepository.loadHistory())
    }

    private fun renderHistory(entries: List<DecisionHistoryEntry>) {
        val emptyState = findViewById<TextView>(R.id.historyEmptyState)
        val container = findViewById<LinearLayout>(R.id.historyContainer)

        container.removeAllViews()

        if (entries.isEmpty()) {
            emptyState.visibility = View.VISIBLE
            return
        }

        emptyState.visibility = View.GONE

        entries.forEach { entry ->
            container.addView(buildHistoryCard(entry))
        }
    }

    private fun buildHistoryCard(entry: DecisionHistoryEntry): View {
        val card = layoutInflater.inflate(R.layout.history_item, null)

        card.findViewById<TextView>(R.id.historyTime).text = formatTimestamp(entry.happenedAt)
        card.findViewById<TextView>(R.id.historyHeadline).text = entry.headline
        card.findViewById<TextView>(R.id.historyMeta).text = buildString {
            append(entry.recommendationLabel)
            append(" • Score ")
            append(entry.decisionScore)
            if (!entry.matchedZone.isNullOrBlank()) {
                append(" • ")
                append(entry.matchedZone)
            }
        }
        card.findViewById<TextView>(R.id.historyMessage).text = entry.message

        return card
    }

    private fun formatTimestamp(timestamp: String): String {
        return runCatching {
            OffsetDateTime.parse(timestamp).format(DateTimeFormatter.ofPattern("dd/MM HH:mm"))
        }.getOrElse {
            timestamp
        }
    }
}
