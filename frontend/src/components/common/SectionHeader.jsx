import { Link } from 'react-router-dom';
import { ArrowRightIcon } from '../../assets/icons';

export default function SectionHeader({ title, subtitle, link, linkText = 'See All' }) {
  return (
    <div className="section-header d-flex flex-wrap justify-content-between align-items-end mb-4 gap-2">
      <div>
        <h2 className="section-title">{title}</h2>
        {subtitle && <p className="section-subtitle">{subtitle}</p>}
      </div>
      {link && (
        <Link to={link} className="section-see-all">
          {linkText} <ArrowRightIcon size={16} />
        </Link>
      )}
    </div>
  );
}
