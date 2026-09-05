import { Container } from 'react-bootstrap';

export default function AppDownloadBanner() {
  return (
    <section className="section-app-download">
      <div className="container-lg">
        <div className="app-download-inner align-items-center">
          <div className="app-download-content">
            <span className="app-download-chip">Download Our App</span>
            <h2 className="app-download-title">
              Get 20% Off Your First Order
            </h2>
            <p className="app-download-text">
              Download our mobile app for exclusive deals, easy reordering, and real-time order tracking.
            </p>
            <div className="app-download-badges">
              <button type="button" className="app-store-badge" disabled>
                <span className="badge-icon">&#9654;</span>
                <span className="badge-text">
                  <small>Download on the</small>
                  <strong>App Store</strong>
                </span>
              </button>
              <button type="button" className="app-store-badge" disabled>
                <span className="badge-icon">&#9654;</span>
                <span className="badge-text">
                  <small>Get it on</small>
                  <strong>Google Play</strong>
                </span>
              </button>
            </div>
          </div>
          <div className="app-download-image-col">
            <div className="app-phone-mockup">
              <div className="app-phone-screen">
                <div className="app-phone-header" />
                <div className="app-phone-card" />
                <div className="app-phone-card" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
