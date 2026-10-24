<!-- ========== LOGIN MODAL ========== -->
<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content p-2 p-sm-4">
      <div class="modal-header">
        <h5 class="modal-title" id="loginModalLabel">Log In</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="loginForm">
          <div id="loginError" class="alert alert-danger d-none" role="alert" tabindex="-1"></div>
          <div class="mb-3">
            <label for="loginEmail" class="form-label">Email</label>
            <input type="email" class="form-control" id="loginEmail" required autocomplete="email" aria-describedby="loginEmailFeedback">
            <div class="invalid-feedback" id="loginEmailFeedback"></div>
          </div>
          <div class="mb-3">
            <label for="loginPassword" class="form-label">Password</label>
            <input type="password" class="form-control" id="loginPassword" required autocomplete="current-password" aria-describedby="loginPasswordFeedback">
            <div class="invalid-feedback" id="loginPasswordFeedback"></div>
          </div>
          <div class="text-end mb-2">
            <button type="button" class="btn btn-link p-0 btn-forgot-password" data-bs-dismiss="modal">Forgot your password?</button>
          </div>
          <button type="submit" class="btn btn-primary w-100 rounded-pill mb-2">Log In</button>
        </form>
        <div class="text-center mt-3">
          <span class="text-body-secondary">No account?</span>
          <button class="btn btn-link p-0 ms-1 btn-signup" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#signupModal">Sign up</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ========== SIGNUP MODAL ========== -->
<div class="modal fade" id="signupModal" tabindex="-1" aria-labelledby="signupModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content p-2 p-sm-4">
      <div class="modal-header">
        <h5 class="modal-title" id="signupModalLabel">Create Account</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="signupForm">
          <div id="signupError" class="alert alert-danger d-none" role="alert" tabindex="-1"></div>
          <div class="mb-3">
            <label for="signupName" class="form-label">Name</label>
            <input type="text" class="form-control" id="signupName" required autocomplete="name" aria-describedby="signupNameFeedback">
            <div class="invalid-feedback" id="signupNameFeedback"></div>
          </div>
          <div class="mb-3">
            <label for="signupEmail" class="form-label">Email</label>
            <input type="email" class="form-control" id="signupEmail" required autocomplete="email" aria-describedby="signupEmailFeedback">
            <div class="invalid-feedback" id="signupEmailFeedback"></div>
          </div>
          <div class="mb-3">
            <label for="signupPassword" class="form-label">Password</label>
            <div class="input-group has-validation">
              <input type="password" class="form-control" id="signupPassword" required autocomplete="new-password" aria-describedby="signupPasswordFeedback signupPasswordHelp">
              <button type="button" class="btn btn-outline-secondary btn-toggle-password" data-target="#signupPassword" aria-label="Show password">
                <i class="fas fa-eye" aria-hidden="true"></i>
              </button>
              <div class="invalid-feedback" id="signupPasswordFeedback"></div>
            </div>
            <div class="form-text" id="signupPasswordHelp">Minimum 6 characters</div>
          </div>
          <div class="mb-3">
            <label for="signupConfirmPassword" class="form-label">Confirm Password</label>
            <div class="input-group has-validation">
              <input type="password" class="form-control" id="signupConfirmPassword" required autocomplete="new-password" aria-describedby="signupConfirmPasswordFeedback">
              <button type="button" class="btn btn-outline-secondary btn-toggle-password" data-target="#signupConfirmPassword" aria-label="Show password">
                <i class="fas fa-eye" aria-hidden="true"></i>
              </button>
              <div class="invalid-feedback" id="signupConfirmPasswordFeedback"></div>
            </div>
          </div>
          <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" id="signupPrivacyConsent" required aria-describedby="signupPrivacyConsentFeedback">
            <label class="form-check-label" for="signupPrivacyConsent">
              I have read and agree to the
              <a href="privacy.php" target="_blank" rel="noopener">Privacy Policy</a>.
              I explicitly consent to the collection and processing of my age, contact
              number, and driver's license details to verify my identity, evaluate
              rental eligibility, and manage my bookings.
            </label>
            <div class="invalid-feedback" id="signupPrivacyConsentFeedback"></div>
          </div>
          <button type="submit" class="btn btn-success w-100 rounded-pill">Create Account</button>
        </form>
        <div class="text-center mt-3">
          <span class="text-body-secondary">Already have an account?</span>
          <button class="btn btn-link p-0 ms-1 btn-goto-login" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#loginModal">Log in</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ========== FORGOT PASSWORD MODAL ========== -->
<div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-labelledby="forgotPasswordModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content p-2 p-sm-4">
      <div class="modal-header">
        <h5 class="modal-title" id="forgotPasswordModalLabel">Reset Password</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="forgotError" class="alert alert-danger d-none" role="alert" tabindex="-1"></div>

        <!-- Step 1: Request Reset Code -->
        <div id="forgotStep1">
          <p class="text-body-secondary">Enter your email address and we'll send you a 6-digit code to reset your password.</p>
          <form id="forgotStep1Form">
            <div class="mb-3">
              <label for="forgotEmail" class="form-label">Email</label>
              <input type="email" class="form-control" id="forgotEmail" required autocomplete="email" aria-describedby="forgotEmailFeedback">
              <div class="invalid-feedback" id="forgotEmailFeedback"></div>
            </div>
            <button type="submit" class="btn btn-primary w-100 rounded-pill">Send Reset Code</button>
          </form>
          <div class="text-center mt-3">
            <span class="text-body-secondary">Remember your password?</span>
            <button class="btn btn-link p-0 ms-1 btn-goto-login" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#loginModal">Log in</button>
          </div>
        </div>

        <!-- Step 2: Enter Code -->
        <div id="forgotStep2" class="d-none">
          <p class="text-body-secondary" id="forgotStep2Instruction" aria-live="polite">We've sent a 6-digit code to your email.</p>
          <form id="forgotStep2Form">
            <div class="mb-3">
              <label for="forgotCode" class="form-label">6-digit code</label>
              <input type="text" class="form-control" id="forgotCode" required inputmode="numeric" maxlength="6" pattern="[0-9]{6}" autocomplete="one-time-code" aria-describedby="forgotCodeFeedback">
              <div class="invalid-feedback" id="forgotCodeFeedback"></div>
            </div>
            <button type="submit" class="btn btn-primary w-100 rounded-pill">Verify Code</button>
          </form>
          <div class="text-center mt-3">
            <span class="text-body-secondary" id="forgotResendStatus" role="status">Didn't receive a code?</span>
            <button type="button" class="btn btn-link p-0 ms-1" id="btnResendCode">Resend</button>
            <span class="text-body-secondary mx-1">&middot;</span>
            <button type="button" class="btn btn-link p-0" id="btnForgotBack">Back</button>
          </div>
        </div>

        <!-- Step 3: Set New Password -->
        <div id="forgotStep3" class="d-none">
          <form id="forgotStep3Form">
            <div class="mb-3">
              <label for="forgotNewPassword" class="form-label">New Password</label>
              <div class="input-group has-validation">
                <input type="password" class="form-control" id="forgotNewPassword" required autocomplete="new-password" aria-describedby="forgotNewPasswordFeedback forgotNewPasswordHelp">
                <button type="button" class="btn btn-outline-secondary btn-toggle-password" data-target="#forgotNewPassword" aria-label="Show password">
                  <i class="fas fa-eye" aria-hidden="true"></i>
                </button>
                <div class="invalid-feedback" id="forgotNewPasswordFeedback"></div>
              </div>
              <div class="form-text" id="forgotNewPasswordHelp">Minimum 6 characters</div>
            </div>
            <div class="mb-3">
              <label for="forgotConfirmPassword" class="form-label">Confirm New Password</label>
              <div class="input-group has-validation">
                <input type="password" class="form-control" id="forgotConfirmPassword" required autocomplete="new-password" aria-describedby="forgotConfirmPasswordFeedback">
                <button type="button" class="btn btn-outline-secondary btn-toggle-password" data-target="#forgotConfirmPassword" aria-label="Show password">
                  <i class="fas fa-eye" aria-hidden="true"></i>
                </button>
                <div class="invalid-feedback" id="forgotConfirmPasswordFeedback"></div>
              </div>
            </div>
            <button type="submit" class="btn btn-success w-100 rounded-pill">Reset Password</button>
          </form>
        </div>

      </div>
    </div>
  </div>
</div>
