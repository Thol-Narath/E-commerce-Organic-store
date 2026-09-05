import { Link } from 'react-router-dom';
import { LeafIcon, ArrowRightIcon } from '../../assets/icons';

export default function AboutUsSection() {
  return (
    <section className="section-about py-5">
      <div className="container-lg">
        <div className="about-grid align-items-center">
          <div className="about-image-col">
            <div className="about-image-frame">
              <div className="about-image-placeholder">
                <LeafIcon size={80} className="about-leaf-icon" />
              </div>
            </div>
            <div className="about-experience-badge">
              <span className="about-exp-number">15+</span>
              <span className="about-exp-text">Years of Experience</span>
            </div>
          </div>

          <div className="about-content-col">
            <span className="about-chip">About Us</span>
            <h2 className="about-title">
              We Are The Best Organic Food Provider
            </h2>
            <p className="about-text">
              We are committed to providing the freshest, highest quality organic
              produce directly from local farms to your table. Our mission is to make
              healthy eating accessible, convenient, and enjoyable for everyone.
            </p>
            <div className="about-features">
              <div className="about-feature-item">
                <span className="about-feature-check">&#10003;</span>
                <span>100% Certified Organic Products</span>
              </div>
              <div className="about-feature-item">
                <span className="about-feature-check">&#10003;</span>
                <span>Farm Fresh Quality Guaranteed</span>
              </div>
              <div className="about-feature-item">
                <span className="about-feature-check">&#10003;</span>
                <span>Free Delivery on Orders Over $50</span>
              </div>
            </div>
            <Link to="/about" className="btn btn-custom-orange mt-3">
              Learn More <ArrowRightIcon size={16} />
            </Link>
          </div>
        </div>
      </div>
    </section>
  );
}
