package br.com.rotadepico.companion

import android.Manifest
import android.content.ComponentName
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.provider.Settings
import android.service.notification.NotificationListenerService
import android.view.View
import android.widget.Button
import android.widget.EditText
import android.widget.LinearLayout
import android.widget.Spinner
import android.widget.TextView
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import br.com.rotadepico.companion.data.DecisionHistoryRepository
import br.com.rotadepico.companion.data.SettingsRepository
import br.com.rotadepico.companion.model.DecisionHistoryEntry
import br.com.rotadepico.companion.network.AuthApiClient
import br.com.rotadepico.companion.service.UberNotificationListenerService
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
    private var isRegisterMode = false

    private lateinit var authCard: LinearLayout
    private lateinit var loggedInBar: LinearLayout
    private lateinit var loggedInUserLabel: TextView
    private lateinit var loggedInActionsContainer: LinearLayout
    private lateinit var subscriptionPendingContainer: LinearLayout

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
        val authStatusValue = findViewById<TextView>(R.id.authStatusValue)
        val authActionButton = findViewById<Button>(R.id.authActionButton)
        val authModeToggle = findViewById<TextView>(R.id.authModeToggle)
        val registerFieldsContainer = findViewById<LinearLayout>(R.id.registerFieldsContainer)
        val registerName = findViewById<EditText>(R.id.registerNameField)
        val registerPhone = findViewById<EditText>(R.id.registerPhoneField)
        val registerCity = findViewById<EditText>(R.id.registerCityField)
        val registerVehicleType = findViewById<Spinner>(R.id.registerVehicleTypeSpinner)
        val registerWorkShift = findViewById<Spinner>(R.id.registerWorkShiftSpinner)
        val registerPasswordConfirmation = findViewById<EditText>(R.id.registerPasswordConfirmationField)
        val openOnboardingButton = findViewById<Button>(R.id.openOnboardingButton)
        val logoutButton = findViewById<Button>(R.id.logoutButton)
        val saveButton = findViewById<Button>(R.id.saveSettingsButton)
        val openSettingsButton = findViewById<Button>(R.id.openSettingsButton)
        val notificationAccessButton = findViewById<Button>(R.id.notificationAccessButton)
        val overlayAccessButton = findViewById<Button>(R.id.overlayAccessButton)
        val notificationPermissionButton = findViewById<Button>(R.id.notificationPermissionButton)
        val advancedSettingsToggle = findViewById<TextView>(R.id.advancedSettingsToggle)
        val advancedSettingsContainer = findViewById<LinearLayout>(R.id.advancedSettingsContainer)

        authCard = findViewById(R.id.authCard)
        loggedInBar = findViewById(R.id.loggedInBar)
        loggedInUserLabel = findViewById(R.id.loggedInUserLabel)
        loggedInActionsContainer = findViewById(R.id.loggedInActionsContainer)
        subscriptionPendingContainer = findViewById(R.id.subscriptionPendingContainer)

        apiBaseUrl.setText(settingsRepository.apiBaseUrl())
        bearerToken.setText(settingsRepository.bearerToken())
        deviceId.setText(settingsRepository.deviceId())
        loginEmail.setText(settingsRepository.userEmail())

        applyAuthState(loggedIn = settingsRepository.bearerToken().isNotBlank())

        authModeToggle.setOnClickListener {
            isRegisterMode = !isRegisterMode
            registerFieldsContainer.visibility = if (isRegisterMode) View.VISIBLE else View.GONE
            authActionButton.text = getString(if (isRegisterMode) R.string.register_button else R.string.login_button)
            authModeToggle.text = getString(if (isRegisterMode) R.string.switch_to_login else R.string.switch_to_register)
        }

        authActionButton.setOnClickListener {
            authStatusValue.text = getString(
                if (isRegisterMode) R.string.register_status_loading else R.string.auth_status_loading
            )

            val resolvedBaseUrl = apiBaseUrl.text.toString().ifBlank { settingsRepository.apiBaseUrl() }
            val wasRegisterMode = isRegisterMode

            scope.launch {
                runCatching {
                    withContext(Dispatchers.IO) {
                        val client = AuthApiClient(resolvedBaseUrl)

                        if (wasRegisterMode) {
                            client.register(
                                name = registerName.text.toString(),
                                email = loginEmail.text.toString(),
                                phone = registerPhone.text.toString(),
                                city = registerCity.text.toString(),
                                vehicleType = registerVehicleType.selectedItem?.toString().orEmpty(),
                                workShift = registerWorkShift.selectedItem?.toString().orEmpty(),
                                password = loginPassword.text.toString(),
                                passwordConfirmation = registerPasswordConfirmation.text.toString()
                            )
                        } else {
                            client.login(
                                email = loginEmail.text.toString(),
                                password = loginPassword.text.toString()
                            )
                        }
                    }
                }.onSuccess { auth ->
                    settingsRepository.saveAuthSession(
                        apiBaseUrl = resolvedBaseUrl,
                        bearerToken = auth.token,
                        deviceId = deviceId.text.toString().ifBlank { settingsRepository.deviceId() },
                        userName = auth.user.name,
                        userEmail = auth.user.email
                    )

                    bearerToken.setText(auth.token)

                    Toast.makeText(
                        this@MainActivity,
                        getString(if (wasRegisterMode) R.string.register_toast_success else R.string.login_toast_success, auth.user.name),
                        Toast.LENGTH_LONG
                    ).show()

                    loginPassword.text.clear()
                    registerPasswordConfirmation.text.clear()
                    registerFieldsContainer.visibility = View.GONE
                    isRegisterMode = false
                    authActionButton.text = getString(R.string.login_button)
                    authModeToggle.text = getString(R.string.switch_to_register)

                    applyAuthState(loggedIn = true)
                    applySubscriptionState(ready = auth.user.isReady)
                    updateStatus()
                }.onFailure {
                    val errorMessage = it.message
                        ?: getString(if (wasRegisterMode) R.string.register_status_error else R.string.auth_status_error)

                    authStatusValue.text = errorMessage
                    Toast.makeText(this@MainActivity, errorMessage, Toast.LENGTH_LONG).show()
                }
            }
        }

        logoutButton.setOnClickListener {
            val resolvedBaseUrl = settingsRepository.apiBaseUrl()
            val token = settingsRepository.bearerToken()

            scope.launch {
                if (token.isNotBlank()) {
                    runCatching {
                        withContext(Dispatchers.IO) { AuthApiClient(resolvedBaseUrl).logout(token) }
                    }
                }

                settingsRepository.clearSession()
                bearerToken.setText("")
                loginEmail.setText("")
                loginPassword.text.clear()

                applyAuthState(loggedIn = false)
                applySubscriptionState(ready = true)
                Toast.makeText(this@MainActivity, R.string.logout_toast, Toast.LENGTH_SHORT).show()
                updateStatus()
            }
        }

        openOnboardingButton.setOnClickListener {
            val resolvedBaseUrl = apiBaseUrl.text.toString().ifBlank { settingsRepository.apiBaseUrl() }
            startActivity(Intent(Intent.ACTION_VIEW, Uri.parse("$resolvedBaseUrl/onboarding")))
        }

        openSettingsButton.setOnClickListener {
            startActivity(Intent(this, SettingsActivity::class.java))
        }

        saveButton.setOnClickListener {
            settingsRepository.save(
                apiBaseUrl = apiBaseUrl.text.toString(),
                bearerToken = bearerToken.text.toString(),
                deviceId = deviceId.text.toString()
            )
            syncAccountFromServer(notifyOnFailure = true)
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

        advancedSettingsToggle.setOnClickListener {
            val expanded = advancedSettingsContainer.visibility == View.VISIBLE
            advancedSettingsContainer.visibility = if (expanded) View.GONE else View.VISIBLE
            advancedSettingsToggle.text = getString(
                if (expanded) R.string.advanced_settings_show else R.string.advanced_settings_hide
            )
        }

        updateStatus()
    }

    override fun onResume() {
        super.onResume()
        updateStatus()

        if (settingsRepository.bearerToken().isNotBlank()) {
            syncAccountFromServer(notifyOnFailure = false)
        }

        if (settingsRepository.hasNotificationAccess()) {
            NotificationListenerService.requestRebind(
                ComponentName(this, UberNotificationListenerService::class.java)
            )
        }
    }

    private fun applyAuthState(loggedIn: Boolean) {
        authCard.visibility = if (loggedIn) View.GONE else View.VISIBLE
        loggedInBar.visibility = if (loggedIn) View.VISIBLE else View.GONE
        loggedInActionsContainer.visibility = if (loggedIn) View.VISIBLE else View.GONE

        if (loggedIn) {
            val name = settingsRepository.userName().ifBlank { settingsRepository.userEmail() }
            loggedInUserLabel.text = name
        } else {
            subscriptionPendingContainer.visibility = View.GONE
        }
    }

    private fun applySubscriptionState(ready: Boolean) {
        subscriptionPendingContainer.visibility = if (ready) View.GONE else View.VISIBLE
    }

    private fun syncAccountFromServer(notifyOnFailure: Boolean) {
        val resolvedBaseUrl = settingsRepository.apiBaseUrl()
        val token = settingsRepository.bearerToken()

        if (token.isBlank()) {
            applyAuthState(loggedIn = false)
            updateStatus()
            return
        }

        scope.launch {
            runCatching {
                withContext(Dispatchers.IO) { AuthApiClient(resolvedBaseUrl).me(token) }
            }.onSuccess { user ->
                settingsRepository.saveAuthSession(
                    apiBaseUrl = resolvedBaseUrl,
                    bearerToken = token,
                    deviceId = settingsRepository.deviceId(),
                    userName = user.name,
                    userEmail = user.email
                )

                applyAuthState(loggedIn = true)
                applySubscriptionState(ready = user.isReady)
                updateStatus()
            }.onFailure {
                if (notifyOnFailure) {
                    val message = it.message ?: getString(R.string.settings_load_error)
                    Toast.makeText(this@MainActivity, message, Toast.LENGTH_LONG).show()
                }
            }
        }
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
