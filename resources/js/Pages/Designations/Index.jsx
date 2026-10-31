import LookupIndex from '../../Components/Staffs/LookupIndex';

export default function DesignationsIndex({ items, unassigned }) {
    return <LookupIndex title="Designations" description="The job titles staff hold, such as Head Teacher, Class Teacher or Accountant." noun="designation" baseUrl="/designations" items={items} unassigned={unassigned} />;
}