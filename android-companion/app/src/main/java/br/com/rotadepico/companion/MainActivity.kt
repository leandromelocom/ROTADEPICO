package br.com.rotadepico.companion

import android.Manifest
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.provider.Settings
import android.widget.Button
import android.widget.EditText
import android.widget.TextView
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import br.com.rotadepico.companion.data.SettingsRepository

class MainActivity : AppCompatActivity() {

    private lateinit var settingsRepository: SettingsRepository

    private val notificationsPermissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { updateStatus() }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        settingsRepository = SettingsRepository(this)

        val apiBaseUrl = findViewById<EditText>(R.id.apiBaseUrlField)
        val bearerToken = findViewById<EditText>(R.id.bearerTokenField)
        val deviceId = findViewById<EditText>(R.id.deviceIdField)
        val saveButton = findViewById<Button>(R.id.saveSettingsButton)
        val notificationAccessButton = findViewById<Button>(R.id.notificationAccessButton)
        val overlayAccessButton = findViewById<Button>(R.id.overlayAccessButton)
        val notificationPermissionButton = findViewById<Button>(R.id.notificationPermissionButton)

        apiBaseUrl.setText(settingsRepository.apiBaseUrl())
        bearerToken.setText(settingsRepository.bearerToken())
        deviceId.setText(settingsRepository.deviceId())

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
        findViewById<TextView>(R.id.tokenStatusValue).text =
            if (settingsRepository.bearerToken().isBlank()) getString(R.string.status_pending) else getString(R.string.status_ready)
        findViewById<TextView>(R.id.listenerStatusValue).text =
            if (settingsRepository.hasNotificationAccess()) getString(R.string.status_ready) else getString(R.string.status_pending)
        findViewById<TextView>(R.id.overlayStatusValue).text =
            if (Settings.canDrawOverlays(this)) getString(R.string.status_ready) else getString(R.string.status_pending)
    }
}
