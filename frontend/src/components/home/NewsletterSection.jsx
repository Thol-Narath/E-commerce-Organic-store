import { useState } from 'react';
import { MailIcon } from '../../assets/icons';
import { newsletterService } from '../../services/newsletterService';

export default function NewsletterSection() {
  const [email, setEmail] = useState('');
  const [subscribing, setSubscribing] = useState(false);
  const [message, setMessage] = useState(null);
  const [error, setError] = useState(false);

  const handleSubscribe = async (e) => {
    e.preventDefault();
    if (!email.trim() || subscribing) return;
    setSubscribing(true);
    setMessage(null);
    setError(false);
    try {
      await newsletterService.subscribe(email.trim());
      setMessage('Thank you for subscribing! Watch your inbox for fresh deals.');
      setEmail('');
    } catch {
      setError(true);
      setMessage('Something went wrong. Please try again.');
    } finally {
      setSubscribing(false);
    }
  };

  return (
    <section className="newsletter-section">
      <div className="container-lg">
        <div className="newsletter-card">
          <div className="newsletter-icon" aria-hidden="true">
            <MailIcon size={26} />
          </div>
          <div className="newsletter-copy">
            <h2 className="newsletter-title">Get Fresh Deals in Your Inbox</h2>
            <p className="newsletter-subtitle">
              Subscribe for exclusive offers, new products, and weekly deals.
            </p>
          </div>
          <form className="newsletter-form" onSubmit={handleSubscribe}>
            <input
              type="email"
              className="newsletter-input"
              placeholder="Enter your email address"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              aria-label="Email address"
              required
            />
            <button type="submit" className="btn btn-newsletter" disabled={subscribing}>
              {subscribing ? 'Subscribing…' : 'Subscribe'}
            </button>
          </form>
          {message && (
            <p className={`newsletter-message ${error ? 'newsletter-message-error' : ''}`} role="status">
              {message}
            </p>
          )}
        </div>
      </div>
    </section>
  );
}